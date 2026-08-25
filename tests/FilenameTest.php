<?php
/**
 * Filename tests.
 *
 * @package ArrayPress\S3Signer
 */

declare( strict_types=1 );

namespace ArrayPress\S3Signer\Tests;

use ArrayPress\S3Signer\Support\Filename;
use PHPUnit\Framework\TestCase;

/**
 * Things that are true of a filename whatever it is for.
 *
 * Nothing here knows about Content-Disposition, headers or S3 — that is the
 * test for whether a function belongs in this class rather than in
 * ContentDisposition, which deals with what a *header* can carry.
 */
final class FilenameTest extends TestCase {

	/**
	 * A Windows path gives up its last segment on any host.
	 *
	 * basename() only understands the host's separator, so on Linux a name
	 * of `..\windows\system32\evil.exe` comes back whole.
	 */
	public function test_a_windows_path_is_reduced_on_any_host(): void {
		$this->assertSame( 'evil.exe', Filename::basename( '..\\windows\\system32\\evil.exe' ) );
		$this->assertSame( 'report.pdf', Filename::basename( '/var/tmp/report.pdf' ) );
		$this->assertSame( 'report.pdf', Filename::basename( 'report.pdf' ) );
	}

	/**
	 * An extension is letters and digits, lower-cased and short.
	 *
	 * Anything else in it is the rest of an attack rather than a file type.
	 */
	public function test_an_extension_is_reduced_to_something_usable(): void {
		$this->assertSame( 'pdf', Filename::extension( 'report.PDF' ) );
		$this->assertSame( 'zip', Filename::extension( 'archive.zip' ) );
		$this->assertSame( '', Filename::extension( 'no-extension' ) );
		$this->assertSame( 'php', Filename::extension( 'shell.p h p' ) );
		$this->assertSame( 'aaaaaaaaaa', Filename::extension( 'x.' . str_repeat( 'a', 40 ) ) );
	}

	/**
	 * A name shorter than the budget is left alone.
	 */
	public function test_a_short_name_is_left_alone(): void {
		$this->assertSame( 'report.pdf', Filename::clamp( 'report.pdf', 200 ) );
	}

	/**
	 * A long name loses its middle and keeps its extension.
	 *
	 * A file that arrives as `report-of-the-annual` opens in nothing.
	 */
	public function test_a_long_name_keeps_its_extension(): void {
		$clamped = Filename::clamp( str_repeat( 'a', 300 ) . '.pdf', 50 );

		$this->assertLessThanOrEqual( 50, strlen( $clamped ) );
		$this->assertStringEndsWith( '.pdf', $clamped );
	}

	/**
	 * The budget is bytes, because the limits it exists for are.
	 *
	 * Two hundred Han characters is six hundred bytes, and a header length
	 * or a filesystem name limit counts the bytes.
	 */
	public function test_the_budget_is_bytes_not_characters(): void {
		$clamped = Filename::clamp( str_repeat( '漢', 100 ) . '.pdf', 50 );

		$this->assertLessThanOrEqual( 50, strlen( $clamped ) );
		$this->assertGreaterThan( 0, mb_strlen( $clamped, 'UTF-8' ) );
	}

	/**
	 * And the cut never lands in the middle of a character.
	 */
	public function test_a_clamped_name_is_still_valid_utf8(): void {
		foreach ( [ 30, 31, 32, 33, 34, 35 ] as $budget ) {
			$clamped = Filename::clamp( str_repeat( '漢', 100 ) . '.pdf', $budget );

			$this->assertTrue(
				mb_check_encoding( $clamped, 'UTF-8' ),
				sprintf( 'A budget of %d bytes cut a character in half.', $budget )
			);
		}
	}

	/**
	 * Something is always left of the stem.
	 *
	 * A name that is only an extension is a hidden file on most systems.
	 */
	public function test_something_is_always_left_of_the_stem(): void {
		$clamped = Filename::clamp( str_repeat( 'a', 300 ) . '.pdf', 4 );

		$this->assertNotSame( '.pdf', $clamped );
		$this->assertStringStartsWith( 'a', $clamped );
	}

	/**
	 * A stem is everything before the extension.
	 */
	public function test_a_stem_is_everything_before_the_extension(): void {
		$this->assertSame( 'report', Filename::stem( 'report.pdf' ) );
		$this->assertSame( 'archive.tar', Filename::stem( 'archive.tar.gz' ) );
		$this->assertSame( 'no-extension', Filename::stem( 'no-extension' ) );
	}
}
