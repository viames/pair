<?php

namespace Pair;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

abstract class Report {

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
	private ?string $query = NULL;

	/**
	 * Column definitions (header, format).
	 */
	private array $columns = [];

	/**
	 * Rows of cell data, for each row an array with zero-based numeric index.
	 */
	private array $data = [];

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
	protected function addColumn(string $head, ?string $format = NULL): self {

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

		$this->data[] = $indexedCellsValue;

	}

	/**
	 * Generic method for creating an Excel document from an array of data.
	 * @deprecated
	 */
	protected function createSpreadsheetFromData(string $documentTitle, array $data, array $columnsDef): Spreadsheet {

		$spreadsheet = new Spreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$activeSheet = $spreadsheet->getActiveSheet();

		foreach ($columnsDef as $col => $def) {
			if (!isset($def->title)) {
				throw new \Exception('Titolo colonna [' . $col . '] non definito');
			}
			$activeSheet->getCell([$col+1, 1])->setValue($def->title)->getStyle()->getFont()->setBold(TRUE);
		}

		$row = 2;

		foreach ($data as $o) {

			foreach ($columnsDef as $col => $def) {

				$propertyName = $def->property ?? $def->title;
				$value = $o->$propertyName;

				$cell = $activeSheet->getCell([$col+1, $row]);

				if (isset($def->format)) {

					switch ($def->format) {

						case 'numeric':
							$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
							break;

						case 'currency':
							$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
							$cell->getStyle()->getNumberFormat()->setFormatCode('#,##0.00');
							break;

						case 'stringDate':
							$dt = \DateTime::createFromFormat('Y-m-d', $value);
							if (is_a($dt,'DateTime')) {
								$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY');
								$cell->setValue($dt->format('d/m/Y'));
							}
							break;

						case 'stringDateTime':
							$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
							if (is_a($dt,'DateTime')) {
								$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS');
								$cell->setValue($dt->format('d/m/Y H:i:s'));
							}
							break;

						case 'DateTime':
							$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS');
							$cell->setValue($value->format('d/m/Y H:i:s'));
							break;

						case 'string':
						default:
							$cell->setValueExplicit($value, DataType::TYPE_STRING);
							break;

					}

				} else  {

					$cell->setValue($value);

				}

			}

			$row++;

		}

		$spreadsheet->getProperties()
			->setCreator(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setLastModifiedBy(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setTitle($documentTitle)
			->setSubject($documentTitle);

		return $spreadsheet;

	}

	/**
	 * Old function to download the past Excel file.
	 * @deprecated
	 */
	public function downloadLegacy(Spreadsheet $spreadsheet): void {

		$filename = Utilities::localCleanFilename($spreadsheet->getProperties()->getTitle() . '.xls');

		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="' . $filename);
		header('Cache-Control: max-age=0');

		// output the file to the browser
		$xlsWriter = new Xls($spreadsheet);
		$xlsWriter->save('php://output');

	}

	/**
	 * Generic function to process and download the Excel file.
	 */
	public function download(): void {

		// process the Excel file
		$spreadsheet = $this->getSpreadsheet();

		// gets the filename from the document title
		$filename = Utilities::localCleanFilename($spreadsheet->getProperties()->getTitle() . '.xls');

		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="' . $filename);
		header('Cache-Control: max-age=0');

		// output the file to the browser
		$xlsWriter = new Xls($spreadsheet);
		$xlsWriter->save('php://output');

	}

	/**
	 * Set the format and value of a cell in the Excel sheet.
	 */
	private function formatCell(Cell &$cell, $value, ?string $format): void {

		// default is auto format
		if (is_null($format)) {

			$cell->setValue($value);

		}

		// otherwise select the appropriate format
		switch ($format) {

			case 'numeric':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				break;

			case 'currency':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				$cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_EUR);
				break;

			// format the field as a date using a Y-m-d string value
			case 'stringDate':
				$dt = \DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
				if (is_a($dt,'DateTime')) {
					$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY');
					$cell->setValue($dt->format('d/m/Y'));
				}
				break;

			// format the field as date-time using a string Y-m-d H:i:s
			case 'stringDateTime':
				$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
				if (is_a($dt,'DateTime')) {
					$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS');
					$cell->setValue($dt->format('d/m/Y H:i:s'));
				}
				break;

			// format the field as a date using a DateTime object
			case 'Date':
				$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY');
				$cell->setValue(is_a($value,'DateTime') ? $value->format('d/m/Y') : NULL);
				break;

			// format the field as a date-time using a DateTime object
			case 'DateTime':
				$cell->getStyle()->getNumberFormat()->setFormatCode('DD/MM/YYYY HH:MM:SS');
				$cell->setValue(is_a($value,'DateTime') ? $value->format('d/m/Y H:i:s') : NULL);
				break;

			case 'string':
			default:
				$cell->setValueExplicit($value, DataType::TYPE_STRING);
				break;

		}

	}

	/**
	 * Generic method for creating an Excel document from an array of data.
	 */
	public function getSpreadsheet(): Spreadsheet {

		// if there is no data and a query has been set, runs it to populate the data
		if (empty($this->data) and !is_null($this->query)) {
			$this->setDataAndColumnsFromDictionary(Database::load($this->query, [], PAIR_DB_DICTIONARY));
		}

		// create the document and set up the sheet
		$spreadsheet = new Spreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$activeSheet = $spreadsheet->getActiveSheet();

		// set column names (header)
		foreach ($this->columns as $col => $def) {
			$activeSheet->getCell([$col+1, 1])->setValue($def->head)->getStyle()->getFont()->setBold(TRUE);
		}

		// start values from second row
		$row = 2;

		// sets the rows of the table
		foreach ($this->data as $o) {

			// column number (zero-based) and defined properties
			foreach ($this->columns as $col => $def) {

				$value = $o[$col];

				// Cell object to write to
				$cell = $activeSheet->getCell([$col+1, $row]);

				$this->formatCell($cell, $value, $def->format ?? NULL);

			}

			$row++;

		}

		// set document properties
		$spreadsheet->getProperties()
			->setCreator(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setLastModifiedBy(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setTitle($this->title)
			->setSubject($this->subject);

		return $spreadsheet;

	}

	/**
	 * Defines a particular column by its index zero-based.
	 */
	protected function setColumn(int $index, string $head, ?string $format = NULL): self {

		$column = new \stdClass;
		$column->head = $head;

		// if no format specified, use an Excel default
		if ($format) {
			$column->format = $format;
		}

		$this->columns[$index] = $column;

		return $this;

	}

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
	 * Set up a SQL query that getSpreadsheet() will execute to easily populate data and columns.
	 */
	protected function setQuery(string $query): self {

		$this->query = $query;

		return $this;

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