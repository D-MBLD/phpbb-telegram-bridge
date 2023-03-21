<?php
/**
 *
 * Telegram Bridge. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2022, Edgar Bolender
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace eb\telegram\core;

//See https://area51.phpbb.com/docs/dev/master/extensions/tutorial_testing.html
//phpunit docu:
//https://phpunit.de/manual/6.5/en/test-doubles.html#test-doubles.stubs.examples.StubTest3.php

/** Test the formatting of telegram input*/
class format_test extends \phpbb_test_case
{
	/** @var \eb\telegram\core\commands */
	private $commands;

	public function setUp(): void
	{
		parent::setUp();
		$this->commands = $this->getMockBuilder('\eb\telegram\core\commands')
			->disableOriginalConstructor()
			->setMethodsExcept(['format_text'])
			->getMock();
	}

	//no formatting at all
	public function test_plain()
	{
		$input = "Some text";
		$formatted = $this->commands->format_text($input, array());
		//No change expected;
		$this->assertEquals($formatted, $input);
	}

	//multiple formats one after the other
	public function test_sequential()
	{
		$input = 'A bold italic underlined text';
        $entities = json_decode( '[{' .
                '"offset": 2,' .
                '"length": 4,' .
                '"type": "bold"' .
            '},{' .
                '"offset": 7,' .
                '"length": 6,' .
                '"type": "italic"' .
			'},{' .
                '"offset": 14,' .
                '"length": 10,' .
                '"type": "underline"' .
            '}]');
		$expected = 'A [b]bold[/b] [i]italic[/i] [u]underlined[/u] text';
		$formatted = $this->commands->format_text($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	//Multiple formats nested
	public function test_nested()
	{
		$input = 'A_bold_italic_underlined_italic_bold_text';
        $entities = json_decode('[{' .
					'"offset": 2,' .
					'"length": 5,' .
					'"type": "bold"' .
				'},{' .
					'"offset": 7,' .
					'"length": 7,' .
					'"type": "bold"' .
				'},{' .
					'"offset": 7,' .
					'"length": 7,' .
					'"type": "italic"' .
				'},{' .
					'"offset": 14,' .
					'"length": 22,' .
					'"type": "bold"' .
				'},{' .
					'"offset": 14,' .
					'"length": 17,' .
					'"type": "italic"' .
				'},{' .
					'"offset": 14,' .
					'"length": 11,' .
					'"type": "underline"' .
				'}]');
		$expected = 'A_[b]bold_[i]italic_[u]underlined_[/u]italic[/i]_bold[/b]_text';
		$formatted = $this->commands->format_text($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	/** Multiple formats overlapping: bold starts, italic starts, bold ends, italic ends.
	 *  (Will usually be split already by telegram)
	 */
	public function test_overlapping()
	{
		$input = 'A bold italic bold_end italic_end text';
        $entities = json_decode('[{' .
					'"offset": 2,' .
					'"length": 12,' .
					'"type": "bold"' .
				'},{' .
					'"offset": 7,' .
					'"length": 16,' .
					'"type": "italic"' .
				'}]');
		$expected = 'A [b]bold [i]italic [/b]bold_end [/i]italic_end text';
		$formatted = $this->commands->format_text($input, $entities);
		$this->assertEquals($expected, $formatted);

		//This is how telegram would send it:
		$entities = json_decode('[{' .
				'"offset": 2,' .
				'"length": 5,' .
				'"type": "bold"' .
			'},{' .
				'"offset": 7,' .
				'"length": 16,' .
				'"type": "italic"' .
			'},{' .
				'"offset": 7,' .
				'"length": 6,' .
				'"type": "bold"' .
			'}]');
		$expected = 'A [b]bold [/b][i][b]italic[/b] bold_end [/i]italic_end text';
		$formatted = $this->commands->format_text($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	/** Test with unicode-characters.
	 */
	public function test_umlauts()
	{
		$input = 'Check formäätting with ÄÖÜ.';
		$input = "Check form\u{00e4}\u{00e4}tting with \u{00c4}\u{00d6}\u{00dc}.";
        $entities = json_decode('[{' .
					'"offset": 6,' .
					'"length": 11,' .
					'"type": "bold"' .
				'},{' .
					'"offset": 23,' .
					'"length": 3,' .
					'"type": "italic"' .
				'}]');
		$expected = 'Check [b]formäätting[/b] with [i]ÄÖÜ[/i].';
		$formatted = $this->commands->format_text($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

}
