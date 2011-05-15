<?php
/**
 * A drop-in replacement class for particletree's PHP profiler for cases where
 *	instead of HTML output the profiler results should be written to a file or
 *	perhaps shown as plain text.
 *
 * @author Martins Pilsetnieks
 * @link http://particletree.com/features/php-quick-profiler/
 */
	class PHPQuickProfiler
	{
		private $StartTime = 0;

		public function __construct($StartTime)
		{
			$this -> StartTime = $StartTime ? $StartTime : self::getMicroTime();

			Console::Init();
		}

		/**
		 * Formats all of the collected data and returns it for output
		 *
		 * @param mixed DB (for standard PHPQuickProfiler's compatibility)
		 * @param mixed MasterDB (for standard PHPQuickProfiler's compatibility)
		 *
		 * @return string Formatted profiler output
		 */
		public function display($DB = null, $MasterDB = null)
		{
			print_r(Console::$Data);
		}

		public static function getMicroTime()
		{
			return microtime(true);
		}
	}
?>