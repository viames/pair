<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Exceptions\PairException;
use Pair\Http\UploadedFile;
use Pair\Tests\Support\TestCase;

/**
 * Covers uploaded file metadata normalization and local storage behavior.
 */
class UploadedFileTest extends TestCase {

	/**
	 * Verify trusted local files expose normalized metadata and can be moved to a safe filename.
	 */
	public function testFromLocalFileExposesMetadataAndMovesFile(): void {

		$contents = $this->pngBytes();
		$source = $this->createTemporaryUploadSource($contents);
		$destinationDirectory = TEMP_PATH . 'uploaded-file-test';
		$this->removeDirectory($destinationDirectory);

		$upload = UploadedFile::fromLocalFile($source, '../Résumé Image.PNG', 'image/png', 'avatar');
		$checksum = $upload->checksum();
		$destination = $upload->moveTo($destinationDirectory);

		$this->assertSame('avatar', $upload->fieldName());
		$this->assertSame('../Résumé Image.PNG', $upload->clientFilename());
		$this->assertSame('resume-image.png', $upload->safeClientFilename());
		$this->assertSame('png', $upload->extension());
		$this->assertSame('image', $upload->mediaCategory());
		$this->assertSame(md5($contents), $checksum);
		$this->assertSame($destinationDirectory . DIRECTORY_SEPARATOR . 'resume-image.png', $destination);
		$this->assertFileExists($destination);
		$this->assertSame($contents, file_get_contents($destination));

	}

	/**
	 * Verify local moves avoid overwriting existing files unless explicitly requested.
	 */
	public function testMoveToCreatesUniqueFilenameWhenDestinationExists(): void {

		$destinationDirectory = TEMP_PATH . 'uploaded-file-existing-test';
		$this->removeDirectory($destinationDirectory);

		if (!is_dir($destinationDirectory)) {
			mkdir($destinationDirectory, 0775, true);
		}

		file_put_contents($destinationDirectory . DIRECTORY_SEPARATOR . 'report.pdf', 'existing');

		$source = $this->createTemporaryUploadSource('new');
		$upload = UploadedFile::fromLocalFile($source, '../../report.pdf', 'application/pdf');
		$destination = $upload->moveTo($destinationDirectory);

		$this->assertSame($destinationDirectory . DIRECTORY_SEPARATOR . 'report-1.pdf', $destination);
		$this->assertSame('existing', file_get_contents($destinationDirectory . DIRECTORY_SEPARATOR . 'report.pdf'));
		$this->assertSame('new', file_get_contents($destination));

	}

	/**
	 * Verify upload error entries remain inspectable and fail when storage is attempted.
	 */
	public function testUploadErrorEntryReportsReadableMessage(): void {

		$upload = UploadedFile::fromArray([
			'name' => 'avatar.png',
			'tmp_name' => '',
			'error' => UPLOAD_ERR_NO_FILE,
			'size' => 0,
			'type' => 'image/png',
		], 'avatar');

		$this->assertFalse($upload->isOk());
		$this->assertSame('No file was submitted for upload', $upload->errorMessage());
		$this->assertSame('image', $upload->mediaCategory());

		$this->expectException(PairException::class);
		$this->expectExceptionMessage('Upload error');

		$upload->moveTo(TEMP_PATH . 'uploaded-file-error-test');

	}

	/**
	 * Verify missing global upload fields fail with an explicit framework exception.
	 */
	public function testFromGlobalsRequiresDeclaredField(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionMessage('Upload field avatar was not found');

		UploadedFile::fromGlobals('avatar');

	}

	/**
	 * Verify dangerous client filenames are reduced to safe storage names.
	 */
	public function testSanitizeFilenameRemovesPathsNullBytesAndUnsafeExtensionCharacters(): void {

		$this->assertSame('file.env', UploadedFile::sanitizeFilename('../.env'));
		$this->assertSame('invoice-final.pdf', UploadedFile::sanitizeFilename("C:\\fakepath\\Invoice Final.PD%F\0"));
		$this->assertSame('file', UploadedFile::sanitizeFilename('***'));

	}

	/**
	 * Create a temporary local file that can stand in for a trusted upload source.
	 */
	private function createTemporaryUploadSource(string $contents): string {

		$source = tempnam(TEMP_PATH, 'upload-source-');

		if (false === $source) {
			$this->fail('Unable to create a temporary upload source.');
		}

		file_put_contents($source, $contents);

		return $source;

	}

	/**
	 * Return bytes for a valid 1x1 PNG image.
	 */
	private function pngBytes(): string {

		$contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);

		if (!is_string($contents)) {
			$this->fail('Unable to decode PNG fixture bytes.');
		}

		return $contents;

	}

}
