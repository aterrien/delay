<?php
namespace f2r\FPM;

use Psr\Log\LoggerInterface;

class Delay
{
    /**
     * @var array|null
     */
    private static $callbacks = null;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    private static $logger = null;

    private static function registerShutdown()
    {
        \register_shutdown_function(function(){
            // Finish client request if it's possible
            if (function_exists('\fastcgi_finish_request')) {
                \fastcgi_finish_request();
            }

            // reorder by priority
            usort(Delay::$callbacks, function ($a, $b) {
                return $a['priority'] - $b['priority'];
            });
            
            foreach (Delay::$callbacks as $callback) {
                try {
                    \call_user_func_array($callback['callback'], $callback['params']);
                } catch (\Throwable $e) {
                    // Use for PHP 7 and PHP 5 compatibility. 
                    if (Delay::$logger !== null) {
                        Delay::$logger->error($e->getMessage());
                    }
                } catch (\Exception $e) {
                    if (Delay::$logger !== null) {
                        Delay::$logger->error($e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * Because, after sending request to user, it's not possible to perform some "echo", attach a logger in order to
     * log callback exceptions
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * Register a callback to be executed during script shutdown
     *
     * @param callable $callback    The callable to be called.
     * @param array    $params      The parameters to be passed to the callback, as an indexed array.
     * @param int      $priority    Callback priority (order by asc). Negative value have higher priority than positive
     */
    public static function register(callable $callback, array $params = [], $priority = 0)
    {
        if (self::$callbacks === null) {
            self::registerShutdown();
            self::$callbacks = [];
        }
        self::$callbacks[] = [
            'callback' => $callback,
            'params' => $params,
            'priority' => $priority
        ];
    }
}
