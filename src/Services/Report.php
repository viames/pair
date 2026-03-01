<?php

namespace Pair\Services;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Models\Locale;
use Pair\Orm\Database;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * This class is a base for creating Excel reports, using PhpSpreadsheet (https://phpspreadsheet.readthedocs.io/).
 * It is not responsible for data retrieval or business logic.
 * It can be extended to create custom reports, or used directly by setting columns and rows and
 * supports saving the report to a file or sending it to the browser for download.
 * It supports direct CSV download, and CSV-to-Excel conversion if the csv2xlsx utility is available.
 * Requires the PhpSpreadsheet library: composer require phpoffice/phpspreadsheet
 */
abstract class Report {

	private const CSV_DEFAULT_DELIMITER = ',';
	private const CSV_DEFAULT_ENCLOSURE = '"';
	private const CSV_DEFAULT_EOL = "\r\n";
	private const CSV_DEFAULT_ESCAPE = '';
	private const DATA_STREAM_MEMORY_BYTES = 2097152;
	private const DOWNLOAD_FORMAT_CSV = 'CSV';
	private const DOWNLOAD_FORMAT_XLSX = 'XLSX';

	/**
	 * Document title.
	 */
	protected string $title;

	/**
	 * Sheet subject.
	 */
   	protected string $subject;

	/**
	 * Contains a SQL query to execute.
	 */
	private ?string $query = null;

	/**
	 * Parameters bound to query execution.
	 */
	private array $queryParams = [];

	/**
	 * Tracks if query rows were already loaded.
	 */
	private bool $queryLoaded = false;

	/**
	 * Column definitions (header, format).
	 */
	private array $columns = [];

	/**
	 * Number of rows added to the report.
	 */
	private int $rowsCount = 0;

	/**
	 * Temporary stream that stores serialized rows to reduce memory usage.
	 */
	private ?\SplTempFileObject $rowsStorage = null;

	/**
	 * Contains the name of the builder library class.
	 */
	private string $library = 'PhpSpreadsheet';

	/**
	 * Output format used when download() is called.
	 */
	private string $downloadFormat = self::DOWNLOAD_FORMAT_XLSX;

	/**
	 * CSV delimiter character.
	 */
	private string $csvDelimiter = self::CSV_DEFAULT_DELIMITER;

	/**
	 * CSV enclosure character.
	 */
	private string $csvEnclosure = self::CSV_DEFAULT_ENCLOSURE;

	/**
	 * CSV line ending.
	 */
	private string $csvEol = self::CSV_DEFAULT_EOL;

	/**
	 * CSV escape character.
	 */
	private string $csvEscape = self::CSV_DEFAULT_ESCAPE;

	/**
	 * Populates the title and subject defaults of the Report document.
	 */
	public function __construct() {

		$this->title = 'report-' . date('Ymd-His');
		$this->subject = 'Data';

	}

	/**
	 * Adds to a property of the class, the specification of a column to be created, numbering it
	 * with a zero-based index.
	 */
	protected function addColumn(string $head, ?string $format = null): self {

		$column = new \stdClass;

		// column header
		$column->head = $head;

		// if no format specified, use an Excel default
		if ($format) {
			$column->format = $format;
		}

		$this->columns[] = $column;

		return $this;

	}

	/**
	 * Adds a row of data supplied as an array of values.
	 */
	protected function addRow(array $indexedCellsValue): void {

		$this->ensureRowsStorage();
		$this->rowsStorage->fwrite($this->encodeRow($indexedCellsValue));
		$this->rowsCount++;

	}

	/**
	 * Hook for custom processing before saving the Excel document.
	 */
	protected function beforeSave(): void {}

	/**
	 * Hook for custom processing after saving the Excel document.
	 */
	protected function afterSave(): void {}

	/**
	 * Return the number of rows of this Report.
	 */
	protected function countRows(): int {

		$this->populateDataFromQuery();

		return $this->rowsCount;

	}

	/**
	 * Decode a serialized row from storage.
	 */
	private function decodeRow(string $line): ?array {

		$payload = base64_decode(rtrim($line, "\r\n"), true);

		if (false === $payload) {
			return null;
		}

		$row = unserialize($payload, ['allowed_classes' => true]);

		return is_array($row) ? $row : null;

	}

	/**
	 * Ensure row storage stream is ready for writes.
	 */
	private function ensureRowsStorage(): void {

		if (!is_null($this->rowsStorage)) {
			return;
		}

		$this->rowsStorage = new \SplTempFileObject(self::DATA_STREAM_MEMORY_BYTES);

	}

	/**
	 * Encode a row in a storage-safe representation.
	 */
	private function encodeRow(array $row): string {

		return base64_encode(serialize($row)) . "\n";

	}

	/**
	 * Iterate stored rows without loading all data in memory.
	 */
	private function getRows(): \Generator {

		if (is_null($this->rowsStorage)) {
			return;
		}

		$this->rowsStorage->rewind();

		while (!$this->rowsStorage->eof()) {

			$line = $this->rowsStorage->fgets();

			if ('' === $line or "\n" === $line or "\r\n" === $line) {
				continue;
			}

			$row = $this->decodeRow($line);

			if (!is_null($row)) {
				yield $row;
			}

		}

	}

	/**
	 * Public function to process the report document, save it to disk in a temporary file
	 * and send it to the browser for download.
	 */
	public function download(): void {

		$isCsv = (self::DOWNLOAD_FORMAT_CSV == $this->downloadFormat);

		// gets the filename from the document title
		$filePath = TEMP_PATH . Utilities::cleanFilename($this->title . ($isCsv ? '.csv' : '.xlsx'));

		$this->save($filePath);

		header('Content-Type: ' . ($isCsv ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
		header('Content-Length: ' . filesize($filePath));
		header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
		header('Cache-Control: max-age=0');
		readfile($filePath);

		unlink($filePath);

	}

	/**
	 * Formats a boolean value for display in the Excel sheet.
	 */
	protected function formatBooleanCell(bool $value): string {

		return (
			$value
			? Translator::do('WORD_TRUE', null, FALSE, 'True')
			: Translator::do('WORD_FALSE', null, FALSE, 'False')
		);

	}

	/**
	 * Set the format and value of a cell in the Excel sheet.
	 */
	private function formatCell(Cell &$cell, mixed $value, null|string|Callable $format = null): void {

		// default is auto format
		if (is_null($format)) {

			$cell->setValue($value);
			return;

		} else if (is_callable($format)) {

			$cell->setValue($format($value));
			return;

		}

		// otherwise select the appropriate format
		switch ($format) {

			case 'int':
			case 'integer':
			case 'numeric':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				break;

			case 'currency':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				$cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_EUR);
				break;

			case 'stringDate':
			case 'stringDateTime':
			case 'Date':
			case 'DateTime':
				$cell->setValue($this->formatDateCell($value, $format));
				break;

			case 'bool':
			case 'boolean':
				$cell->setValue($this->formatBooleanCell((bool)(int)$value));
				break;

			case 'string':
			default:
				$cell->setValueExplicit($value, DataType::TYPE_STRING);
				break;

		}

	}

	/**
	 * Formats a date value for display in the Excel sheet.
	 */
	protected function formatDateCell($value, string $format): ?string {

		switch ($format) {

			case 'stringDate':
				$dt = \DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
				if (is_a($dt,'DateTime')) {
					return $dt->format('d/m/Y');
				}
				break;

			case 'stringDateTime':
				$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
				if (is_a($dt,'DateTime')) {
					return $dt->format('d/m/Y H:i:s');
				}
				break;

			case 'Date':
				return is_a($value,'DateTime') ? $value->format('d/m/Y') : null;
				break;

			case 'DateTime':
				return is_a($value,'DateTime') ? $value->format('d/m/Y H:i:s') : null;
				break;

		}

		return null;

	}

	/**
	 * Generic method for creating an Excel document from an array of data.
	 */
	public function getSpreadsheet(): Spreadsheet {

		$this->populateDataFromQuery();

		// create the document and set up the sheet
		$spreadsheet = new Spreadsheet();
		$activeSheet = $spreadsheet->setActiveSheetIndex(0);

		// set the locale
		$app = Application::getInstance();
		$cUser = $app->currentUser;
		Settings::setLocale($cUser
			? $cUser->getLocale()->getRepresentation('_')
			: Locale::getDefault()->getRepresentation('_')
		);

		// set column names (header)
		foreach ($this->columns as $col => $def) {
			$activeSheet->getCell([$col+1, 1])->setValue($def->head)->getStyle()->getFont()->setBold(true);
		}

		// start values from second row
		$row = 2;

		// sets the rows of the table
		foreach ($this->getRows() as $o) {

			// column number (zero-based) and defined properties
			foreach ($this->columns as $col => $def) {

				$value = $o[$col] ?? null;

				// Cell object to write to
				$cell = $activeSheet->getCell([$col+1, $row]);

				$this->formatCell($cell, $value, $def->format ?? null);

			}

			$row++;

		}

		$creator = Env::get('APP_NAME') . ' ' . Env::get('APP_VERSION');

		// set document properties
		$spreadsheet->getProperties()
			->setCreator($creator)
			->setLastModifiedBy($creator)
			->setTitle($this->title)
			->setSubject($this->subject);

		return $spreadsheet;

	}

	/**
	 * Save the report file to disk on the specified absolute path.
	 * The output extension decides the file type.
	 */
	public function save(string $filePath): bool {

		// hook for custom processing
		$this->beforeSave();

		$fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		if ('csv' == $fileExtension) {

			$this->saveCsv($filePath);

		} else if ('CSV' == $this->library and !is_null(Utilities::getExecutablePath('csv2xlsx', 'CSV2XLSX_PATH'))) {

			$this->saveCsvAndConvert($filePath);

		} else {

			$writer = new Xlsx($this->getSpreadsheet());
			$writer->save($filePath);

		}

		// hook for custom processing
		$this->afterSave();

		return file_exists($filePath);

	}

	/**
	 * Backward compatibility method.
	 */
	public function setBuilder(string $library): self {

		$this->setLibrary($library);

		return $this;

	}

	/**
	 * Set CSV options using RFC 4180 defaults, overriding only provided values.
	 */
	public function setCsvOptions(?string $delimiter = null, ?string $enclosure = null, ?string $escape = null, ?string $eol = null): self {

		if (!is_null($delimiter) and strlen($delimiter) === 1) {
			$this->csvDelimiter = $delimiter;
		}

		if (!is_null($enclosure) and strlen($enclosure) === 1) {
			$this->csvEnclosure = $enclosure;
		}

		if (!is_null($escape) and (strlen($escape) === 1 or '' === $escape)) {
			$this->csvEscape = $escape;
		}

		if (!is_null($eol) and '' !== $eol) {
			$this->csvEol = $eol;
		}

		return $this;

	}

	/**
	 * Set only CSV delimiter while keeping all other CSV options unchanged.
	 */
	public function setCsvDelimiter(string $delimiter): self {

		$this->setCsvOptions($delimiter);

		return $this;

	}

	/**
	 * Set the output format that download() will send to the browser.
	 */
	public function setDownloadFormat(string $format): self {

		$format = strtoupper(trim($format));

		if (in_array($format, [self::DOWNLOAD_FORMAT_XLSX, self::DOWNLOAD_FORMAT_CSV], true)) {
			$this->downloadFormat = $format;
		}

		return $this;

	}

	/**
	 * Set an output format alias for backward readability in child classes.
	 */
	public function setOutputFormat(string $format): self {

		$this->setDownloadFormat($format);

		return $this;

	}

	/**
	 * Defines a particular column by its index zero-based.
	 */
	protected function setColumn(int $index, string $head, null|string|Callable $format = null): self {

		$column = new \stdClass;
		$column->head = $head;

		// if no format specified, use an Excel default
		if ($format) {
			$column->format = $format;
		}

		$this->columns[$index] = $column;

		return $this;

	}

	/**
	 * Set column headers and cell values using an associative array.
	 */
	protected function setDataAndColumnsFromDictionary(array $dictionary): self {

		// empty list
		if (!isset($dictionary[0])) {
			return $this;
		}

		// extracts the property names of the first object
		$varNames = array_keys($dictionary[0]);

		// adds column headers
		foreach ($varNames as $varName) {
			$this->addColumn($varName);
		}

		// populates all cells in all rows
		foreach ($dictionary as $line) {

			$this->addRow(array_values($line));

		}

		return $this;

	}

	/**
	 * Set column headers and cell values using an array of objects.
	 */
	protected function setDataAndColumnsFromObjects(array $objectList): self {

		// empty list
		if (!isset($objectList[0])) {
			return $this;
		}

		// extracts the property names of the first object
		$varNames = array_keys(get_object_vars($objectList[0]));

		// adds column headers
		foreach ($varNames as $varName) {
			$this->addColumn($varName);
		}

		// populate the cells
		$this->setDataFromObjects($objectList);

		return $this;

	}

	/**
	 * Set cell values using an object array and column map.
	 */
	protected function setDataFromObjects(array $objectList): self {

		// create an array [column_name => column_index]
		$columns = array_flip(array_map(function($o) { return $o->head; }, $this->columns));

		// assigns values to cells based on the column name
		foreach ($objectList as $object) {

			$row = [];

			foreach ($columns as $name => $index) {
				$row[$index] = $object->$name;
			}

			$this->addRow($row);

		}

		return $this;

	}

	/**
	 * Set the internal builder used for XLSX generation.
	 */
	protected function setLibrary(string $library): self {

		$this->library = $library;

		return $this;

	}

	/**
	 * Populate columns and rows from query when data has not been manually assigned.
	 */
	private function populateDataFromQuery(): void {

		if ($this->queryLoaded or is_null($this->query) or $this->rowsCount > 0) {
			return;
		}

		$hasRows = false;

		foreach (Database::iterateDictionary($this->query, $this->queryParams) as $line) {

			if (!$hasRows) {
				if (empty($this->columns)) {
					foreach (array_keys($line) as $varName) {
						$this->addColumn($varName);
					}
				}
				$hasRows = true;
			}

			$this->addRow(array_values($line));

		}

		$this->queryLoaded = true;

	}

	/**
	 * Set up a SQL query that report builders will execute to easily populate data and columns.
	 */
	protected function setQuery(string $query, array $params = []): self {

		$this->query = $query;
		$this->queryParams = $params;
		$this->queryLoaded = false;

		return $this;

	}

	/**
	 * Save the CSV file to disk on the specified absolute path.
	 */
	private function saveCsv(string $filePath, bool $forceStrings = false): void {

		$this->populateDataFromQuery();

		$filePointer = fopen($filePath, 'w');

		if (false === $filePointer) {
			return;
		}

		// write the header
		fputcsv($filePointer, array_map(function($o) { return $o->head; }, $this->columns), $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape, $this->csvEol);

		$dateFormats = ['stringDate', 'stringDateTime', 'Date', 'DateTime'];
		$numericFormats = ['int', 'integer', 'numeric', 'currency'];

		// write the data
		foreach ($this->getRows() as $row) {

			foreach ($row as $key => $value) {

				$format = $this->columns[$key]->format ?? null;

				if (in_array($format, $dateFormats, true)) {
					$row[$key] = $this->formatDateCell($value, $format);
				} else if ($forceStrings and !in_array($format, $numericFormats, true)) {
					$row[$key] = '\'' . $value; // forced to string in converter
				}

			}

			fputcsv($filePointer, $row, $this->csvDelimiter, $this->csvEnclosure, $this->csvEscape, $this->csvEol);

		}

		fclose($filePointer);

	}

	/**
	 * Save the CSV file and convert it to Excel.
	 */
	private function saveCsvAndConvert(string $filePath): void {

		$csvFile = $filePath . '.csv';

		$this->saveCsv($csvFile, true);

		// convert to Excel
		Utilities::convertCsvToExcel($csvFile, $filePath, $this->csvDelimiter);

	}

	/**
	 * Set the subject of the document.
	 */
	public function setSubject(string $subject): self {

		$this->subject = $subject;

		return $this;

	}

	/**
	 * Set the title of the document.
	 */
	public function setTitle(string $title): self {

		$this->title = $title;

		return $this;

	}

}
