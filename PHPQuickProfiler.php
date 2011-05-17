<?php
/**
 * A drop-in replacement class for particletree's PHP profiler for cases where
 *	instead of HTML output the profiler results should be written to a file or
 *	perhaps shown as plain text.
 *
 * @author Martins Pilsetnieks
 * @link http://particletree.com/features/php-quick-profiler/
 */
	if (!defined('endl'))
	{
		define('endl', "\n");
	}

 	class PHPQuickProfiler
	{
		private $StartTime = 0;

		private $Data = array(
			'Files' => array(
				'TotalSize' => 0,
				'List' => array()
			),
			'Queries' => array(
				'TotalTime' => 0,
				'List' => array()
			)
		);

		public function __construct($StartTime)
		{
			$this -> StartTime = $StartTime ? $StartTime : self::getMicroTime();

			Console::Init();
		}

		private function GatherFileData()
		{
			$Files = get_included_files();
			$FileList = array();

			foreach ($Files as $File)
			{
				$Size = filesize($File);

				$this -> Data['Files']['List'][] = array(
					'Name' => $File,
					'Size' => self::GetReadableFileSize($Size)
				);

				$this -> Data['Files']['TotalSize'] += $Size;
			}
		}

		private function GatherQueryData()
		{
			foreach (Console::$Data['Main']['Query'] as $Query)
			{
				$this -> Data['Queries']['List'][] = array(
					'SQL' => $Query['SQL'],
					'Time' => self::GetReadableTime($Query['Time'])
				);

				$this -> Data['Queries']['TotalTime'] += $Query['Time'];
			}
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
			// Gather file data
			$this -> GatherFileData();
			$this -> GatherQueryData();

			echo '<pre>';

			// !Query data output
			$QueryCount = count($this -> Data['Queries']['List']);
			$QueryCountLen = strlen($QueryCount);
			echo '--- Queries: ---'.endl;
			echo endl;
			echo 'Total of '.$QueryCount.' queries, '.self::GetReadableTime($this -> Data['Queries']['TotalTime']).endl;
			echo endl;

			foreach ($this -> Data['Queries']['List'] as $Index => $Query)
			{
				echo str_pad($Index + 1, $QueryCountLen + 1, ' ').'| ';
				echo str_pad($Query['Time'], 10, ' ').'| ';
				echo self::GetWrappedText($Query['SQL'], 100, 0, $QueryCountLen + 17).endl;
				echo endl;
			}

			echo endl.endl;

			// !File data output
			$FileCount = count($this -> Data['Files']['List']);
			$FileCountLen = strlen($FileCount);

			echo '--- Files: ---'.endl;
			echo endl;
			echo 'Total of '.$FileCount.' files, '.self::GetReadableFileSize($this -> Data['Files']['TotalSize']).endl;
			echo endl;

			foreach ($this -> Data['Files']['List'] as $Index => $File)
			{
				echo str_pad($Index + 1, $FileCountLen + 1, ' ').'| ';
				echo str_pad($File['Size'], 10, ' ').'| ';
				echo $File['Name'].endl;
			}

			echo '</pre>';
		}

		public static function getMicroTime()
		{
			return microtime(true);
		}

		private static function GetReadableFileSize($Size)
		{
			$Units = array('B', 'kB', 'MB', 'GB', 'TB');

			$Format = $retstring = '%01.2f %s';

			$UnitCtr = 0;
			while ($Size > 1024)
			{
				$Size /= 1024;
				$UnitCtr++;
			}

			if ($UnitCtr == 0)
			{
				return $Size.' B';
			}
			else
			{
				return round($Size, 3).' '.$Units[$UnitCtr];
			}
		}

		/**
		 * @Param float Time in seconds
		 */
		private static function GetReadableTime($Time)
		{
			if ($Time <= 0)
			{
				return '0';
			}

			$Units = array('s', 'ms', 'ys', 'ns', 'ps', 'fs', 'as');

			$UnitCtr = 0;
			while ($Time < 1)
			{
				$Time *= 1000;
				$UnitCtr++;
			}

			return round($Time, 2).' '.$Units[$UnitCtr];
		}

		private static function GetWrappedText($Text, $LineWidth, $FirstLineIndent = 0, $OtherLineIndent = 0)
		{
			$Text = preg_replace('{(\s)+}isS', ' ', $Text);

			$Text = wordwrap($Text, $LineWidth, endl, false);

			if ($FirstLineIndent)
			{
				$Text = str_repeat(' ', $FirstLineIndent);
			}
			if ($OtherLineIndent)
			{
				$Text = str_replace(endl, endl.str_repeat(' ', $OtherLineIndent), $Text);
			}

			return $Text;
		}
	}
?>