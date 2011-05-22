<?php
	class Console extends PHPQuickProfiler
	{
		const ENTRY_TYPE_LOG = 'Log';
		const ENTRY_TYPE_ERROR = 'Error';
		const ENTRY_TYPE_MEMORY = 'Memory';
		const ENTRY_TYPE_SPEED = 'Speed';
		const ENTRY_TYPE_QUERY = 'Query';
		
		private static $CurrentLoop = 'Main';
		private static $Loops = array('Main');

		protected static $Data = array();

		private static $ContMode = false;
		private static $OutputDest = false;
		private static $OutputFile = false;

		protected static function Init($ContMode = false, $OutputDest = self::OUTPUT_DEST_DISPLAY, $OutputFile = false)
		{
			self::$ContMode = $ContMode;
			self::$OutputDest = $OutputDest;
			self::$OutputFile = $OutputFile;

			self::$Data['Main'] = array(
				self::ENTRY_TYPE_LOG => array(),
				self::ENTRY_TYPE_ERROR => array(),
				self::ENTRY_TYPE_MEMORY => array(),
				self::ENTRY_TYPE_SPEED => array(),
				self::ENTRY_TYPE_QUERY => array()
			);
		}

		public static function log($Message)
		{
			if (self::$ContMode)
			{
				self::WriteEntry(self::ENTRY_TYPE_LOG, $Entry);
			}
			else
			{
				self::$Data[self::$CurrentLoop][self::ENTRY_TYPE_LOG][] = $Message;
			}
		}

		public static function logError(Exception $E = null, $ErrorMessage = null)
		{
			if (!$E && !$ErrorMessage)
			{
				return false;
			}
		
			if ($ErrorMessage)
			{
				$Message = $ErrorMessage;
				$File = '';
				$Line = 0;
			}
			else
			{
				$Message = $E -> getMessage();
				$File = $E -> getFile();
				$Line = $E -> getLine();
			}

			if (self::$ContMode)
			{
				self::WriteEntry(self::ENTRY_TYPE_ERROR, $Message.' in '.$File.':'.$Line);
			}
			else
			{
				self::$Data[self::$CurrentLoop][self::ENTRY_TYPE_ERROR][] = array(
					'Message' => $Message,
					'File' => $File,
					'Line' => $Line
				);
			}
		}

		public static function logMemory($Variable = null, $Name = null)
		{
			$Memory = memory_get_usage();
			if ($Variable)
			{
				$Memory = strlen(serialize($Variable));
			}

			if (self::$ContMode)
			{
				self::WriteEntry(self::ENTRY_TYPE_MEMORY, $Name.' ('.gettype($Variable).'): '.$Memory);
			}
			else
			{
				self::$Data[self::$CurrentLoop][self::ENTRY_TYPE_MEMORY][] = array(
					'Data' => $Memory,
					'Name' => $Name,
					'Type' => gettype($Variable)
				);
			}
		}

		public static function logSpeed($Name)
		{
			if (self::$ContMode)
			{
				self::WriteEntry(self::ENTRY_TYPE_SPEED, self::getMicroTime().': '.$Name);
			}
			else
			{
				self::$Data[self::$CurrentLoop][self::ENTRY_TYPE_SPEED][] = array(
					'Data' => self::getMicroTime(),
					'Name' => $Name
				);
			}
		}

		public static function logQuery($SQL, $StartTime)
		{
			if (self::$ContMode)
			{
				self::WriteEntry(self::ENTRY_TYPE_QUERY, (self::getMicroTime() - $StartTime).': '.$SQL);
			}
			else
			{
				self::$Data[self::$CurrentLoop][self::ENTRY_TYPE_QUERY][] = array(
					'SQL' => $SQL,
					'Time' => self::getMicroTime() - $StartTime
				);
			}
		}

		public static function logLoop($LoopName, $Iteration = 0, $SnapshotInterval = 10)
		{
			self::$Loops[] = $LoopName;
			self::$CurrentLoop = $LoopName;

			self::$LoopIteration = $Iteration;
			self::$SnapshotInterval = $SnapshotInterval;

			if ($Iteration % $SnapshotInterval == 0)
			{
				self::WriteLoopSnapshot();
			}
		}

		public static function logLoopEnd()
		{
			self::$CurrentLoop = array_pop(self::$Loops);

			// Snapshot is written only if the iteration hasn't yet gotten to the snapshot point.
			//	If it is mod 0, it means that the last loop entry was already at a snapshot interval, so no snapshot entry.
			if (self::$LoopIteration % self::$SnapshotInterval != 0)
			{
				self::WriteLoopSnapshot();
			}

			self::WriteLoopSummary();
		}

		private static WriteEntry($EntryType, $Message)
		{
			if (self::$OutputDest == self::OUTPUT_DEST_FILE)
			{
				file_put_contents(self::$OutputFile, $Message.endl, FILE_APPEND);
			}
			elseif (self::$OutputDest == self::OUTPUT_DEST_DISPLAY)
			{
				echo $Message.endl;
			}
		}

		private static WriteLoopSnapshot()
		{
		}

		private static WriteLoopSummary()
		{
		}

		private static function GetOutput()
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

			return $Output;
		}
	}
?>