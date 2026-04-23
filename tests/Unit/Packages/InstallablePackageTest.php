<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Packages;

use Pair\Exceptions\PairException;
use Pair\Packages\InstallablePackage;
use Pair\Tests\Support\TestCase;

/**
 * Covers installable package manifest and archive guardrails.
 */
class InstallablePackageTest extends TestCase {

	/**
	 * Verify an installer instance can be created before package metadata is known.
	 */
	public function testPackageCanBeInstantiatedWithoutMetadata(): void {

		$package = new InstallablePackage();

		$this->assertNull($package->name);
		$this->assertNull($package->baseFolder);

	}

	/**
	 * Verify new manifests require the package node.
	 */
	public function testReadManifestFileRequiresPackageNode(): void {

		$file = TEMP_PATH . 'legacy-manifest.xml';
		file_put_contents($file, '<manifest><plugin type="Module"><name>legacy</name></plugin></manifest>');

		$this->expectException(PairException::class);
		$this->expectExceptionMessage('package node');

		InstallablePackage::readManifestFile($file);

	}

	/**
	 * Verify generated manifests write the package schema.
	 */
	public function testWriteManifestFileUsesPackageNode(): void {

		$folder = TEMP_PATH . 'package-fixture';
		$this->removeDirectory($folder);
		mkdir($folder, 0775, true);
		file_put_contents($folder . '/controller.php', '<?php');

		$package = new InstallablePackage('Module', 'Fixture', '1.0.0', '2026-04-23', '4.0', $folder);

		$this->assertTrue($package->writeManifestFile());

		$manifest = (string)file_get_contents($folder . '/manifest.xml');

		$this->assertStringContainsString('<package type="Module">', $manifest);
		$this->assertStringNotContainsString('<plugin', $manifest);

	}

	/**
	 * Verify archive paths cannot escape the package root.
	 */
	public function testNormalizeArchivePathRejectsTraversal(): void {

		$this->assertSame('module/controller.php', $this->normalizeArchivePath('module//./controller.php'));

		$this->expectException(PairException::class);
		$this->expectExceptionMessage('parent folders');

		$this->normalizeArchivePath('module/../outside.php');

	}

	/**
	 * Invoke the private archive path normalizer for focused path validation tests.
	 *
	 * @param	string	$path	Archive path to validate.
	 */
	private function normalizeArchivePath(string $path): string {

		$reflection = new \ReflectionMethod(InstallablePackage::class, 'normalizeArchivePath');

		return $reflection->invoke(null, $path);

	}

}
