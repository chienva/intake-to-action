<?php

namespace Tests;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class RedactionNets
{
    public array $events = [];

    public array $channels = [];

    public array $names;

    public string $errorFile;

    private string $previousErrorLog;

    public function __construct(public T4Harness $h)
    {
        $configured = config('logging.channels');
        $names = array_keys($configured);
        foreach ($configured as $channel) {
            if (($channel['driver'] ?? null) === 'stack') {
                expect($channel['channels'])->not->toBeEmpty();
                $names = [...$names, ...$channel['channels']];
            }
        }
        $this->names = array_values(array_unique($names));
        expect($this->names)->not->toBeEmpty();
        app('events')->listen(MessageLogged::class, function (MessageLogged $event) {
            $this->events[] = self::serialize([$event->message, $event->context]);
        });
        $nets = $this;
        Log::extend('t4_capture', function ($app, array $config) use ($nets) {
            $handler = new class($nets, $config['capture_name']) extends AbstractProcessingHandler
            {
                public function __construct(private RedactionNets $nets, private string $channel)
                {
                    parent::__construct();
                }

                protected function write(LogRecord $record): void
                {
                    $this->nets->channels[$this->channel][] = RedactionNets::serialize([$record->message, $record->context, $record->extra]);
                }
            };

            return new Logger($config['capture_name'], [$handler]);
        });
        foreach ($this->names as $name) {
            config(['logging.channels.'.$name => ['driver' => 't4_capture', 'capture_name' => $name]]);
            Log::forgetChannel($name);
        }
        $this->errorFile = $h->root.'/php-error.log';
        $this->previousErrorLog = ini_get('error_log');
        ini_set('error_log', $this->errorFile);
    }

    public static function serialize(mixed $value): string
    {
        return serialize(self::normalize($value, new \SplObjectStorage));
    }

    private static function normalize(mixed $value, \SplObjectStorage $seen): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::normalize($item, $seen), $value);
        }
        if (! is_object($value)) {
            return $value;
        }
        if ($seen->contains($value)) {
            return ['class' => $value::class, 'cycle' => true];
        }
        $seen->attach($value);
        if ($value instanceof \Throwable) {
            return [
                'class' => $value::class, 'message' => $value->getMessage(), 'code' => $value->getCode(),
                'file' => $value->getFile(), 'line' => $value->getLine(), 'trace' => $value->getTraceAsString(),
                'previous' => self::normalize($value->getPrevious(), $seen),
            ];
        }

        return ['class' => $value::class, 'properties' => self::normalize((array) $value, $seen),
            'string' => $value instanceof \Stringable ? (string) $value : null];
    }

    public function restore(): void
    {
        ini_set('error_log', $this->previousErrorLog);
    }

    public function activateErrorLog(): void
    {
        // PHPUnit installs its own target after setUp; redirect at test entry.
        expect(ini_set('error_log', $this->errorFile) !== false)->toBeTrue();
        expect(ini_get('error_log'))->toBe($this->errorFile);
    }

    public function files(): array
    {
        $result = [];
        foreach ([storage_path('logs'), $this->h->root.'/storage/logs'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $result[$file->getPathname()] = file_get_contents($file->getPathname());
                }
            }
        }
        if (file_exists($this->errorFile)) {
            $result[$this->errorFile] = file_get_contents($this->errorFile);
        }

        return $result;
    }

    public function assertAbsent(string $sentinel, ?int $permittedIssuance = null): void
    {
        foreach ($this->events as $record) {
            expect(str_contains($record, $sentinel))->toBeFalse();
        }
        foreach ($this->channels as $records) {
            foreach ($records as $record) {
                expect(str_contains($record, $sentinel))->toBeFalse();
            }
        }
        foreach ($this->files() as $content) {
            expect(str_contains($content, $sentinel))->toBeFalse();
        }
        foreach ($this->h->processes as $index => [$exit, $stdout, $stderr]) {
            if ($index !== $permittedIssuance) {
                expect(str_contains($stdout, $sentinel))->toBeFalse();
            } else {
                expect($exit)->toBe(0);
                expect(preg_match('/\A[0-9]+\|[A-Za-z0-9]+\n\z/', $stdout) === 1)->toBeTrue();
                expect($stdout === $sentinel."\n")->toBeTrue();
            }
            expect(str_contains($stderr, $sentinel))->toBeFalse();
        }
        foreach ($this->h->responses as $response) {
            expect(str_contains($response->getContent(), $sentinel))->toBeFalse();
            expect(str_contains(self::serialize($response->headers->all()), $sentinel))->toBeFalse();
        }
        $scanner = new SqliteColumnScanner;
        $scan = $scanner->scan(DB::connection()->getPdo());
        $scanner->assertCoverage(DB::connection()->getPdo(), $scan);
        foreach ($scan as $values) {
            foreach ($values as $value) {
                expect(str_contains((string) $value, $sentinel))->toBeFalse();
            }
        }
    }
}
