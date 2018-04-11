<?php

namespace React\Tests\EventLoop;

abstract class AbstractLoopTest extends TestCase
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    private $tickTimeout;

    const PHP_DEFAULT_CHUNK_SIZE = 8192;

    public function setUp()
    {
        // It's a timeout, don't set it too low. Travis and other CI systems are slow.
        $this->tickTimeout = 0.02;
        $this->loop = $this->createLoop();
    }

    abstract public function createLoop();

    public function createSocketPair()
    {
        $domain = (DIRECTORY_SEPARATOR === '\\') ? STREAM_PF_INET : STREAM_PF_UNIX;
        $sockets = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        foreach ($sockets as $socket) {
            if (function_exists('stream_set_read_buffer')) {
                stream_set_read_buffer($socket, 0);
            }
        }

        return $sockets;
    }

    public function testAddReadStream()
    {
        list ($input, $output) = $this->createSocketPair();

        $this->loop->addReadStream($input, $this->expectCallableExactly(2));

        fwrite($output, "foo\n");
        $this->tickLoop($this->loop);

        fwrite($output, "bar\n");
        $this->tickLoop($this->loop);
    }

    public function testAddReadStreamIgnoresSecondCallable()
    {
        list ($input, $output) = $this->createSocketPair();

        $this->loop->addReadStream($input, $this->expectCallableExactly(2));
        $this->loop->addReadStream($input, $this->expectCallableNever());

        fwrite($output, "foo\n");
        $this->tickLoop($this->loop);

        fwrite($output, "bar\n");
        $this->tickLoop($this->loop);
    }

    public function testAddReadStreamReceivesDataFromStreamReference()
    {
        $this->received = '';
        $this->subAddReadStreamReceivesDataFromStreamReference();
        $this->assertEquals('', $this->received);

        $this->assertRunFasterThan($this->tickTimeout * 2);
        $this->assertEquals('[hello]X', $this->received);
    }

    /**
     * Helper for above test. This happens in another helper method to verify
     * the loop keeps track of assigned stream resources (refcount).
     */
    private function subAddReadStreamReceivesDataFromStreamReference()
    {
        list ($input, $output) = $this->createSocketPair();

        fwrite($input, 'hello');
        fclose($input);

        $loop = $this->loop;
        $received =& $this->received;
        $loop->addReadStream($output, function ($output) use ($loop, &$received) {
            $chunk = fread($output, 1024);
            if ($chunk === '') {
                $received .= 'X';
                $loop->removeReadStream($output);
                fclose($output);
            } else {
                $received .= '[' . $chunk . ']';
            }
        });
    }

    public function testAddWriteStream()
    {
        list ($input) = $this->createSocketPair();

        $this->loop->addWriteStream($input, $this->expectCallableExactly(2));
        $this->tickLoop($this->loop);
        $this->tickLoop($this->loop);
    }

    public function testAddWriteStreamIgnoresSecondCallable()
    {
        list ($input) = $this->createSocketPair();

        $this->loop->addWriteStream($input, $this->expectCallableExactly(2));
        $this->loop->addWriteStream($input, $this->expectCallableNever());
        $this->tickLoop($this->loop);
        $this->tickLoop($this->loop);
    }

    public function testRemoveReadStreamInstantly()
    {
        list ($input, $output) = $this->createSocketPair();

        $this->loop->addReadStream($input, $this->expectCallableNever());
        $this->loop->removeReadStream($input);

        fwrite($output, "bar\n");
        $this->tickLoop($this->loop);
    }

    public function testRemoveReadStreamAfterReading()
    {
        list ($input, $output) = $this->createSocketPair();

        $this->loop->addReadStream($input, $this->expectCallableOnce());

        fwrite($output, "foo\n");
        $this->tickLoop($this->loop);

        $this->loop->removeReadStream($input);

        fwrite($output, "bar\n");
        $this->tickLoop($this->loop);
    }

    public function testRemoveWriteStreamInstantly()
    {
        list ($input) = $this->createSocketPair();

        $this->loop->addWriteStream($input, $this->expectCallableNever());
        $this->loop->removeWriteStream($input);
        $this->tickLoop($this->loop);
    }

    public function testRemoveWriteStreamAfterWriting()
    {
        list ($input) = $this->createSocketPair();

        $this->loop->addWriteStream($input, $this->expectCallableOnce());
        $this->tickLoop($this->loop);

        $this->loop->removeWriteStream($input);
        $this->tickLoop($this->loop);
    }

    public function testRemoveStreamForReadOnly()
    {
        list ($input, $output) = $this->createSocketPair();

        $this->loop->addReadStream($input, $this->expectCallableNever());
        $this->loop->addWriteStream($output, $this->expectCallableOnce());
        $this->loop->removeReadStream($input);

        fwrite($output, "foo\n");
        $this->tickLoop($this->loop);
    }

    public function testRemoveStreamForWriteOnly()
    {
        list ($input, $output) = $this->createSocketPair();

        fwrite($output, "foo\n");

        $this->loop->addReadStream($input, $this->expectCallableOnce());
        $this->loop->addWriteStream($output, $this->expectCallableNever());
        $this->loop->removeWriteStream($output);

        $this->tickLoop($this->loop);
    }

    public function testRemoveReadAndWriteStreamFromLoopOnceResourceClosesEndsLoop()
    {
        list($stream, $other) = $this->createSocketPair();
        stream_set_blocking($stream, false);
        stream_set_blocking($other, false);

        // dummy writable handler
        $this->loop->addWriteStream($stream, function () { });

        // remove stream when the stream is readable (closes)
        $loop = $this->loop;
        $loop->addReadStream($stream, function ($stream) use ($loop) {
            $loop->removeReadStream($stream);
            $loop->removeWriteStream($stream);
            fclose($stream);
        });

        // close other side
        fclose($other);

        $this->assertRunFasterThan($this->tickTimeout);
    }

    public function testRemoveReadAndWriteStreamFromLoopOnceResourceClosesOnEndOfFileEndsLoop()
    {
        list($stream, $other) = $this->createSocketPair();
        stream_set_blocking($stream, false);
        stream_set_blocking($other, false);

        // dummy writable handler
        $this->loop->addWriteStream($stream, function () { });

        // remove stream when the stream is readable (closes)
        $loop = $this->loop;
        $loop->addReadStream($stream, function ($stream) use ($loop) {
            $data = fread($stream, 1024);
            if ($data !== '') {
                return;
            }

            $loop->removeReadStream($stream);
            $loop->removeWriteStream($stream);
            fclose($stream);
        });

        // send data and close stream
        fwrite($other, str_repeat('.', static::PHP_DEFAULT_CHUNK_SIZE));
        $this->loop->addTimer(0.01, function () use ($other) {
            fclose($other);
        });

        $this->assertRunFasterThan(0.1);
    }

    public function testRemoveReadAndWriteStreamFromLoopWithClosingResourceEndsLoop()
    {
        // get only one part of the pair to ensure the other side will close immediately
        list($stream) = $this->createSocketPair();
        stream_set_blocking($stream, false);

        // dummy writable handler
        $this->loop->addWriteStream($stream, function () { });

        // remove stream when the stream is readable (closes)
        $loop = $this->loop;
        $loop->addReadStream($stream, function ($stream) use ($loop) {
            $loop->removeReadStream($stream);
            $loop->removeWriteStream($stream);
            fclose($stream);
        });

        $this->assertRunFasterThan($this->tickTimeout);
    }

    public function testRemoveInvalid()
    {
        list ($stream) = $this->createSocketPair();

        // remove a valid stream from the event loop that was never added in the first place
        $this->loop->removeReadStream($stream);
        $this->loop->removeWriteStream($stream);

        $this->assertTrue(true);
    }

    /** @test */
    public function emptyRunShouldSimplyReturn()
    {
        $this->assertRunFasterThan($this->tickTimeout);
    }

    /** @test */
    public function runShouldReturnWhenNoMoreFds()
    {
        list ($input, $output) = $this->createSocketPair();

        $loop = $this->loop;
        $this->loop->addReadStream($input, function ($stream) use ($loop) {
            $loop->removeReadStream($stream);
        });

        fwrite($output, "foo\n");

        $this->assertRunFasterThan($this->tickTimeout * 2);
    }

    /** @test */
    public function stopShouldStopRunningLoop()
    {
        list ($input, $output) = $this->createSocketPair();

        $loop = $this->loop;
        $this->loop->addReadStream($input, function ($stream) use ($loop) {
            $loop->stop();
        });

        fwrite($output, "foo\n");

        $this->assertRunFasterThan($this->tickTimeout * 2);
    }

    public function testStopShouldPreventRunFromBlocking()
    {
        $that = $this;
        $this->loop->addTimer(
            1,
            function () use ($that) {
                $that->fail('Timer was executed.');
            }
        );

        $loop = $this->loop;
        $this->loop->futureTick(
            function () use ($loop) {
                $loop->stop();
            }
        );

        $this->assertRunFasterThan($this->tickTimeout * 2);
    }

    public function testIgnoreRemovedCallback()
    {
        // two independent streams, both should be readable right away
        list ($input1, $output1) = $this->createSocketPair();
        list ($input2, $output2) = $this->createSocketPair();

        $called = false;

        $loop = $this->loop;
        $loop->addReadStream($input1, function ($stream) use (& $called, $loop, $input2) {
            // stream1 is readable, remove stream2 as well => this will invalidate its callback
            $loop->removeReadStream($stream);
            $loop->removeReadStream($input2);

            $called = true;
        });

        // this callback would have to be called as well, but the first stream already removed us
        $that = $this;
        $loop->addReadStream($input2, function () use (& $called, $that) {
            if ($called) {
                $that->fail('Callback 2 must not be called after callback 1 was called');
            }
        });

        fwrite($output1, "foo\n");
        fwrite($output2, "foo\n");

        $loop->run();

        $this->assertTrue($called);
    }

    public function testFutureTickEventGeneratedByFutureTick()
    {
        $loop = $this->loop;
        $this->loop->futureTick(
            function () use ($loop) {
                $loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testFutureTick()
    {
        $called = false;

        $callback = function () use (&$called) {
            $called = true;
        };

        $this->loop->futureTick($callback);

        $this->assertFalse($called);

        $this->tickLoop($this->loop);

        $this->assertTrue($called);
    }

    public function testFutureTickFiresBeforeIO()
    {
        list ($stream) = $this->createSocketPair();

        $this->loop->addWriteStream(
            $stream,
            function () {
                echo 'stream' . PHP_EOL;
            }
        );

        $this->loop->futureTick(
            function () {
                echo 'future-tick' . PHP_EOL;
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL . 'stream' . PHP_EOL);

        $this->tickLoop($this->loop);
    }

    public function testRecursiveFutureTick()
    {
        list ($stream) = $this->createSocketPair();

        $loop = $this->loop;
        $this->loop->addWriteStream(
            $stream,
            function () use ($stream, $loop) {
                echo 'stream' . PHP_EOL;
                $loop->removeWriteStream($stream);
            }
        );

        $this->loop->futureTick(
            function () use ($loop) {
                echo 'future-tick-1' . PHP_EOL;
                $loop->futureTick(
                    function () {
                        echo 'future-tick-2' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick-1' . PHP_EOL . 'stream' . PHP_EOL . 'future-tick-2' . PHP_EOL);

        $this->loop->run();
    }

    public function testRunWaitsForFutureTickEvents()
    {
        list ($stream) = $this->createSocketPair();

        $loop = $this->loop;
        $this->loop->addWriteStream(
            $stream,
            function () use ($stream, $loop) {
                $loop->removeWriteStream($stream);
                $loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testFutureTickEventGeneratedByTimer()
    {
        $loop = $this->loop;
        $this->loop->addTimer(
            0.001,
            function () use ($loop) {
                $loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testRemoveSignalNotRegisteredIsNoOp()
    {
        $this->loop->removeSignal(SIGINT, function () { });
        $this->assertTrue(true);
    }

    public function testSignal()
    {
        if (!function_exists('posix_kill') || !function_exists('posix_getpid')) {
            $this->markTestSkipped('Signal test skipped because functions "posix_kill" and "posix_getpid" are missing.');
        }

        $called = false;
        $calledShouldNot = true;

        $timer = $this->loop->addPeriodicTimer(1, function () {});

        $this->loop->addSignal(SIGUSR2, $func2 = function () use (&$calledShouldNot) {
            $calledShouldNot = false;
        });

        $loop = $this->loop;
        $this->loop->addSignal(SIGUSR1, $func1 = function () use (&$func1, &$func2, &$called, $timer, $loop) {
            $called = true;
            $loop->removeSignal(SIGUSR1, $func1);
            $loop->removeSignal(SIGUSR2, $func2);
            $loop->cancelTimer($timer);
        });

        $this->loop->futureTick(function () {
            posix_kill(posix_getpid(), SIGUSR1);
        });

        $this->loop->run();

        $this->assertTrue($called);
        $this->assertTrue($calledShouldNot);
    }

    public function testSignalMultipleUsagesForTheSameListener()
    {
        $funcCallCount = 0;
        $func = function () use (&$funcCallCount) {
            $funcCallCount++;
        };
        $this->loop->addTimer(1, function () {});

        $this->loop->addSignal(SIGUSR1, $func);
        $this->loop->addSignal(SIGUSR1, $func);

        $this->loop->addTimer(0.4, function () {
            posix_kill(posix_getpid(), SIGUSR1);
        });
        $loop = $this->loop;
        $this->loop->addTimer(0.9, function () use (&$func, $loop) {
            $loop->removeSignal(SIGUSR1, $func);
        });

        $this->loop->run();

        $this->assertSame(1, $funcCallCount);
    }

    public function testSignalsKeepTheLoopRunning()
    {
        $loop = $this->loop;
        $function = function () {};
        $this->loop->addSignal(SIGUSR1, $function);
        $this->loop->addTimer(1.5, function () use ($function, $loop) {
            $loop->removeSignal(SIGUSR1, $function);
            $loop->stop();
        });

        $this->assertRunSlowerThan(1.5);
    }

    public function testSignalsKeepTheLoopRunningAndRemovingItStopsTheLoop()
    {
        $loop = $this->loop;
        $function = function () {};
        $this->loop->addSignal(SIGUSR1, $function);
        $this->loop->addTimer(1.5, function () use ($function, $loop) {
            $loop->removeSignal(SIGUSR1, $function);
        });

        $this->assertRunFasterThan(1.6);
    }

    public function testTimerIntervalCanBeFarInFuture()
    {
        // get only one part of the pair to ensure the other side will close immediately
        list($stream) = $this->createSocketPair();

        // start a timer very far in the future
        $timer = $this->loop->addTimer(PHP_INT_MAX, function () { });

        // remove stream and timer when the stream is readable (closes)
        $loop = $this->loop;
        $this->loop->addReadStream($stream, function ($stream) use ($timer, $loop) {
            $loop->removeReadStream($stream);
            $loop->cancelTimer($timer);
        });

        $this->assertRunFasterThan($this->tickTimeout);
    }

    private function assertRunSlowerThan($minInterval)
    {
        $start = microtime(true);

        $this->loop->run();

        $end = microtime(true);
        $interval = $end - $start;

        $this->assertLessThan($interval, $minInterval);
    }

    private function assertRunFasterThan($maxInterval)
    {
        $start = microtime(true);

        $this->loop->run();

        $end = microtime(true);
        $interval = $end - $start;

        $this->assertLessThan($maxInterval, $interval);
    }
}
