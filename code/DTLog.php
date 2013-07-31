<?php
/**
 * Simple consolidated logger that writes to a separate log file
 * (deploytools.log) than debug::log. It just allows us to consolidate
 * configuration and hide it from the individual components.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 7.31.13
 * @package deploytools
 */
class DTLog extends Object
{
	static $log_path;     // relative to BASE_PATH
	static $log_file;
	static $log_level;
	static $date_format;

	private static $logger;

	/**
	 * Singleton - I called it 'logger' in case we want to
	 * plug in Zend_Log at any stage.
	 *
	 * @return DTLog
	 */
	public static function logger() {
		if (!isset(self::$logger)) {
			self::$logger = new DTLog();
		}
		return self::$logger;
	}

	/**
	 * @param $msg
	 */
	public static function info($msg) {
		self::logger()->write($msg, 'INFO');
	}

	/**
	 * @param $msg
	 */
	public static function debug($msg) {
		self::logger()->write($msg, 'DEBUG');
	}

	/**
	 * This is not the prettiest thing ever but it works
	 * for the most basic case.
	 *
	 * @param mixed $message
	 * @param string $type
	 */
	public function write($message, $type='INFO') {
		if ($type == 'DEBUG' && $this->config('log_level') != 'DEBUG') return;

		$filename = $this->getLogPath();
		if ($filename) {
			if (!file_exists($filename)) {
				file_put_contents($filename, '');
				chmod($filename, 0664);
			}

			if (!is_string($message)) $message = print_r($message, true);

			file_put_contents($filename, date($this->config('date_format')) . ' --- ' . $type . ': ' . $message . PHP_EOL, FILE_APPEND);
		}
	}

	/**
	 * @return string
	 */
	protected function getLogPath() {
		$f = $this->config('log_file');
		if (!$f) return '';
		return BASE_PATH . DIRECTORY_SEPARATOR . $this->config('log_path') . DIRECTORY_SEPARATOR . $f;
	}

}