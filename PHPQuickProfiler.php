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
		const OUTPUT_DEST_FILE = 'File';
		const OUTPUT_DEST_DISPLAY = 'Display';
		const OUTPUT_DEST_MYSQL = 'MySQL';

		const OUTPUT_MODE_CONTINUOUS = 'Continuous';
		const OUTPUT_MODE_AT_END = 'AtEnd';

		private $OutputDest = 'Display';
		private $OutputMode = 'AtEnd';
		private $OutputFile = false;

		private $StartTime = 0;
		private $StartMemory = 0;

		private $Data = array(
			'Files' => array(
				'TotalSize' => 0,
				'List' => array()
			),
			'Queries' => array(
				'TotalTime' => 0,
				'List' => array()
			),
			'Speed' => array(
				'TotalTime' => 0,
				'List' => array()
			),
			'Log' => array(),
			'Error' => array()
		);

		/**
		 * @param float Starting time (Unix timestamp, in seconds)
		 * @param array Options
		 */
		public function __construct($StartTime, array $Options = null)
		{
			$this -> StartTime = $StartTime ? $StartTime : self::getMicroTime();
			$this -> StartMemory = memory_get_usage();

			$this -> OutputDest = self::OUTPUT_DEST_DISPLAY;
			if (isset($Options['Dest']) && $Options['Dest'] == self::OUTPUT_DEST_FILE)
			{
				if (isset($Options['File']) && is_writable($Options['File']))
				{
					$this -> OutputFile = $Options['File'];
				}
				if (isset($Options['Dir']) && is_writable($Options['Dir']))
				{
					$this -> OutputFile = $Options['Dir'].'/'.uniqid(date('Ymd').'_', false).'.pqp';
				}

				if ($this -> OutputFile)
				{
					$this -> OutputDest = self::OUTPUT_DEST_FILE;
				}
			}

			$this -> OutputMode = self::OUTPUT_MODE_AT_END;
			if (isset($Options['Mode']) && $Options['Mode'] == self::OUTPUT_MODE_CONTINUOUS)
			{
				$this -> OutputMode = self::OUTPUT_MODE_CONTINUOUS;
			}

			Console::Init(
				($this -> OutputMode == self::OUTPUT_MODE_CONTINUOUS),
				$this -> OutputDest,
				$this -> OutputFile
			);
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

		private function GatherSpeedData()
		{
			foreach (Console::$Data['Main']['Speed'] as $Speed)
			{
				$SpeedTimeDelta = $Speed['Data'] - $this -> StartTime;

				$this -> Data['Speed']['List'][] = array(
					'Name' => $Speed['Name'],
					'Time' => self::GetReadableTime($SpeedTimeDelta)
				);
			}

			$this -> Data['Speed']['TotalTime'] = self::getMicroTime() - $this -> StartTime;
		}

		private function GatherConsoleData()
		{
			foreach (Console::$Data['Main']['Log'] as $Log)
			{
				$this -> Data['Log']['List'][] = array(
					'Message' => $Log
				);
			}

			foreach (Console::$Data['Main']['Error'] as $Error)
			{
				$this -> Data['Error']['List'][] = array(
					'Message' => $Error['Message'].' at '.$Error['File'].' : '.$Error['Line']
				);
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
			if ($this -> OutputMode == self::OUTPUT_MODE_AT_END)
			{
				$Output = Console::GetOutput();

				if ($this -> OutputDest == self::OUTPUT_DEST_DISPLAY)
				{
					echo '<pre>';
					echo $Output;
					echo '</pre>';
				}
				elseif ($this -> OutputDest == self::OUTPUT_DEST_FILE)
				{
					file_put_contents($this -> OutputFile, $Output, FILE_APPEND);
				}
			}
		}

		public static function getMicroTime()
		{
			return microtime(true);
		}

		protected static function GetReadableFileSize($Size, $Precision = 3)
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
				return round($Size, $Precision).' '.$Units[$UnitCtr];
			}
		}

		/**
		 * @Param float Time in seconds
		 */
		protected static function GetReadableTime($Time)
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

		protected static function GetWrappedText($Text, $LineWidth, $FirstLineIndent = 0, $OtherLineIndent = 0)
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