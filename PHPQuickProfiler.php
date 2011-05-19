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
		const OUTPUT_MODE_FILE = 'File';
		const OUTPUT_MODE_DISPLAY = 'Display';

		private $OutputMode = 'Display';
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

		public function __construct($StartTime, array $Options = null)
		{
			$this -> StartTime = $StartTime ? $StartTime : self::getMicroTime();
			$this -> StartMemory = memory_get_usage();

			$this -> OutputMode = self::OUTPUT_MODE_DISPLAY;
			if (isset($Options['Mode']) && $Options['Mode'] == self::OUTPUT_MODE_FILE)
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
					$this -> OutputMode = self::OUTPUT_MODE_FILE;
				}
			}

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
			$Output = '--- '.date('Y-m-d H:i:s').' ---'.endl.endl;

			// !Memory data
			$Output .= '--- Memory ---'.endl;
			$Output .= endl;
			$Output .= 'Currently allocated: '.self::GetReadableFileSize(memory_get_usage(), 6).endl;
			$Output .= 'Total peak: '.self::GetReadableFileSize(memory_get_peak_usage(), 6).endl;
			$Output .= 'Profiled allocated memory: '.self::GetReadableFileSize(memory_get_usage() - $this -> StartMemory, 6).endl;
			$Output .= 'Peak profiled memory: '.self::GetReadableFileSize(memory_get_peak_usage() - $this -> StartMemory, 6).endl;
			$Output .= endl;

			// Gather file data
			$this -> GatherConsoleData();
			$this -> GatherFileData();
			$this -> GatherQueryData();
			$this -> GatherSpeedData();

			// !Log entries
			$LogEntryCount = count($this -> Data['Log']);
			$LogEntryCountLen = strlen($LogEntryCount);
			$Output .= '--- Log entries: ---'.endl;
			$Output .= endl;
			$Output .= $LogEntryCount.' entries'.endl;

			if ($LogEntryCount)
			{
				$Output .= endl;
				foreach ($this -> Data['Log'] as $Index => $Entry)
				{
					$Output .= str_pad($Index + 1, $LogEntryCountLen + 1, ' ').'| ';
					$Output .= $Entry.endl;
				}
			}

			$Output .= endl.endl;

			// !Errors
			$ErrorCount = count($this -> Data['Error']);
			$ErrorCountLen = strlen($ErrorCount);
			$Output .= '--- Errors: ---'.endl;
			$Output .= endl;
			$Output .= $ErrorCount.' errors'.endl;

			if ($ErrorCount)
			{
				$Output .= endl;
				foreach ($this -> Data['Error'] as $Index => $Error)
				{
					$Output .= str_pad($Index + 1, $EntryCountLen + 1, ' ').'| ';
					$Output .= $Error.endl;
				}
			}

			$Output .= endl.endl;

			// !Query data output
			$QueryCount = count($this -> Data['Queries']['List']);
			$QueryCountLen = strlen($QueryCount);
			$Output .= '--- Queries: ---'.endl;
			$Output .= endl;
			$Output .= 'Total of '.$QueryCount.' queries, '.self::GetReadableTime($this -> Data['Queries']['TotalTime']).endl;
			$Output .= endl;

			foreach ($this -> Data['Queries']['List'] as $Index => $Query)
			{
				$Output .= str_pad($Index + 1, $QueryCountLen + 1, ' ').'| ';
				$Output .= str_pad($Query['Time'], 10, ' ').'| ';
				$Output .= self::GetWrappedText($Query['SQL'], 100, 0, $QueryCountLen + 17).endl;
				$Output .= endl;
			}

			$Output .= endl.endl;

			// !File data output
			$FileCount = count($this -> Data['Files']['List']);
			$FileCountLen = strlen($FileCount);

			$Output .= '--- Files: ---'.endl;
			$Output .= endl;
			$Output .= 'Total of '.$FileCount.' files, '.self::GetReadableFileSize($this -> Data['Files']['TotalSize']).endl;
			$Output .= endl;

			foreach ($this -> Data['Files']['List'] as $Index => $File)
			{
				$Output .= str_pad($Index + 1, $FileCountLen + 1, ' ').'| ';
				$Output .= str_pad($File['Size'], 10, ' ').'| ';
				$Output .= $File['Name'].endl;
			}

			$Output .= endl.endl;

			// !Speed count
			$SpeedCountLen = strlen(count($this -> Data['Speed']['List']));
			$Output .= '--- Load time: ---'.endl;
			$Output .= endl;
			$Output .= 'Total time '.self::GetReadableTime($this -> Data['Speed']['TotalTime']).endl;
			$Output .= endl;
			foreach ($this -> Data['Speed']['List'] as $Index => $Speed)
			{
				$Output .= str_pad($Index + 1, $SpeedCountLen + 1, ' ').'| ';
				$Output .= str_pad($Speed['Time'], 10, ' ').'| ';
				$Output .= $Speed['Name'].endl;
			}

			$Output .= endl.endl;

			if ($this -> OutputMode == self::OUTPUT_MODE_DISPLAY)
			{
				echo '<pre>';
				echo $Output;
				echo '</pre>';
			}
			elseif ($this -> OutputMode == self::OUTPUT_MODE_FILE)
			{
				file_put_contents($this -> OutputFile, $Output, FILE_APPEND);
			}
		}

		public static function getMicroTime()
		{
			return microtime(true);
		}

		private static function GetReadableFileSize($Size, $Precision = 3)
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