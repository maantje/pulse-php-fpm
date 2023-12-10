<?php

namespace Maantje\Pulse\PhpFpm\Recorders;

use Adoy\FastCGI\Client;
use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use RuntimeException;

class PhpFpmRecorder
{
    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = SharedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config
    ) {
        //
    }

    /**
     * @throws JsonException
     */
    public function record(SharedBeat $event): void
    {
        $status = $this->fpmStatus();

        $server = $this->config->get('pulse.recorders.'.self::class.'.server_name', gethostname());
        $slug = Str::slug($server);

        $this->pulse->record('active processes', $slug, $status['active processes'], $event->time)->avg()->onlyBuckets();
        $this->pulse->record('total processes', $slug, $status['total processes'], $event->time)->avg()->onlyBuckets();
        $this->pulse->record('idle processes', $slug, $status['idle processes'], $event->time)->avg()->onlyBuckets();
        $this->pulse->record('listen queue', $slug, $status['listen queue'], $event->time)->avg()->onlyBuckets();

        $this->pulse->set('php_fpm', $slug, json_encode([
            ...$status,
            'name' => $server,
        ]));
    }

    /**
     * @throws JsonException
     */
    private function fpmStatus(): array
    {
        [$sock, $url] = $this->fpmConnectionInfo();

        $client = $this->createClient($sock, $url);

        $response = $client->request([
            'REQUEST_METHOD'    => 'GET',
            'SCRIPT_FILENAME'   => $url['path'],
            'SCRIPT_NAME'       => $url['path'],
            'QUERY_STRING'      => 'json',
        ], stdin: false);

        $parts = explode(PHP_EOL, $response);

        return json_decode(end($parts), true, flags: JSON_THROW_ON_ERROR);
    }

    private function createClient($sock, $url): Client
    {
        if ($sock) {
            return new Client("unix://$sock", -1);
        }

        return new Client($url['host'], $url['port']);
    }

    private function fpmConnectionInfo(): array
    {
        $statusPath = $this->config->get('pulse.recorders.'.self::class.'.status_path', 'localhost:9000/status');
        $url = parse_url($statusPath);
        $sock = false;

        if (preg_match('|^unix:(.*.sock)(/.*)$|', $statusPath, $reg)) {
            $url  = parse_url($reg[2]);
            $sock = $reg[1];

            if (!file_exists($sock)) {
                throw new RuntimeException("UDS $sock not found");
            } else if (!is_writable($sock)) {
                throw new RuntimeException("UDS $sock is not writable");
            }
        }

        if (!$url || !isset($url['path'])) {
            throw new RuntimeException('Malformed URI');
        }

        return [$sock, $url];
    }
}
