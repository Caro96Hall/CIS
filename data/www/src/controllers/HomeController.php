<?php
namespace controllers;

use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class HomeController
{

    /** @var ContainerInterface */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function home(Request $request, Response $response, $args)
    {
        return $this->container->get('renderer')->render($response, 'index.phtml', $args);
    }

    public function signup(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $username = $params['newUser'] ?? '';
        if (! ctype_alnum($username)) {
            return $response->withStatus(422, 'Please use only alphanumeric characters in the username.');
        }
        if (strlen($username) < 4) {
            return $response->withStatus(422, 'Please use at least 4 characters in the username.');
        }
        $password = $params['newPass'] ?? '';
        if (strlen($password) < 8) {
            return $response->withStatus(422, 'Please use at least 8 characters in the password.');
        }

        $dir = __DIR__;
        echo self::exec("python2 -u $dir/register_user.py --username $username --password $password");
    }

    /**
     * Execute a command and return it's output.
     * Either wait until the command exits or the timeout has expired.
     *
     * @param string $cmd
     *            Command to execute.
     * @param number $timeout
     *            Timeout in seconds.
     * @return string Output of the command.
     * @throws \Exception
     */
    private static function exec($cmd, $timeout = 60)
    {
        $descriptors = [
            [
                'pipe',
                'r'
            ],
            [
                'pipe',
                'w'
            ],
            [
                'pipe',
                'w'
            ]
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($process)) {
            throw new \Exception('Could not execute process');
        }

        // Set the stdout and stderr streams to non-blocking.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        // Turn the timeout into microseconds.
        $timeout = $timeout * 1000000;

        // Output buffer.
        $buffer = '';
        // While we have time to wait.
        while ($timeout > 0) {
            $start = microtime(true);

            // Wait until we have output or the timer expired.
            $read = [
                $pipes[1]
            ];
            $other = [];
            stream_select($read, $other, $other, 0, $timeout);

            // Get the status of the process.
            // Do this before we read from the stream,
            // so we can't lose the last bit of output if the process dies between these functions.
            $status = proc_get_status($process);

            // Read the contents from the buffer.
            // This function will always return immediately as the stream is non-blocking.
            $buffer .= stream_get_contents($pipes[1]);

            // Subtract the number of microseconds that we waited.
            $timeout -= (microtime(true) - $start) * 1000000;

            if (! $status['running']) {
                // Break from this loop if the process exited before the timeout.
                break;
            }
        }

        // Check if there were any errors.
        $errors = stream_get_contents($pipes[2]);
        if ($timeout <= 0) {
            $errors .= PHP_EOL . 'Request timed out!';
        } else if ($status['exitcode'] != 0) {
            $errors .= PHP_EOL . 'Request failed! (code ' . $status['exitcode'] . ')';
        }

        // Kill the process in case the timeout expired and it's still running.
        // If the process already exited this won't do anything.
        proc_terminate($process, 9);

        // Close all streams.
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        return empty($errors) ? $buffer : $errors;
    }
}