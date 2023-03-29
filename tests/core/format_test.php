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
	/** @var \eb\telegram\core\formatters */
	private $formatters;

	public function setUp(): void
	{
		parent::setUp();
		$this->user = $this->getMockBuilder('\phpbb\user')
			->disableOriginalConstructor()
			->getMock();
		$this->user->host = "server.name"; //expected by generate_board_url()
		$this->config = $this->getMockBuilder('\phpbb\config\config')
		->disableOriginalConstructor()
		->getMock();
		//Config entries expected by generate_board_url()
		$this->config->expects($this->any())
		->method('offsetGet')
		->willReturnMap([ //Map param(s) to return value
			['force_server_vars', true],
			['server_protocol', 'http://'],
			['server_name', 'server.name'],
			['server_port', ''],
			['script_path', '/phpbb'],
			['cookie_secure', ''],
		]);

		$this->formatters = new \eb\telegram\core\formatters();
	}

	//no formatting at all
	public function test_format_input_plain()
	{
		$input = "Some text";
		$formatted = $this->formatters->format_input($input, array());
		//No change expected;
		$this->assertEquals($formatted, $input);
	}

	//multiple formats one after the other
	public function test_format_input_sequential()
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
		$formatted = $this->formatters->format_input($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	//Multiple formats nested
	public function test_format_input_nested()
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
		$formatted = $this->formatters->format_input($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	/** Multiple formats overlapping: bold starts, italic starts, bold ends, italic ends.
	 *  (Will usually be split already by telegram)
	 */
	public function test_format_input_overlapping()
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
		$formatted = $this->formatters->format_input($input, $entities);
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
		$formatted = $this->formatters->format_input($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	/** Test with unicode-characters.
	 */
	public function test_format_input_umlauts()
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
		$formatted = $this->formatters->format_input($input, $entities);
		$this->assertEquals($expected, $formatted);
	}

	/** Test the formatting of a post with lots of
	 * different BBCodes.
	 */
	public function test_format_post_for_telelegram()
	{
		//Function generate_board_url called in formatters->format_post_for_telegram
		//expects some globals to be set.
		global $config, $user;
		$config = $this->config;
		$user = $this->user;
		
		 //This is the typical DB-content for a post.
		$input = <<<'EOD'
<r>mention -&gt; <QUOTE><s>[quote]</s>Quote something<e>[/quote]</e></QUOTE>
bot_command -&gt; /should not be treated as command <br/>
url1 -&gt; <URL url="http://google.com"><s>[url=http://google.com]</s>BBCode-Url with text<e>[/url]</e></URL><br/>
url2 -&gt; <URL url="http://google.com"><s>[url]</s>http://google.com<e>[/url]</e></URL> (BBCode-Url without text)<br/>
url3 without bbcode: <URL url="http://google.com">http://google.com</URL> <br/>
email -&gt; <EMAIL email="email@for.you"><s>[email]</s>email@for.you<e>[/email]</e></EMAIL> (Email in BBCode) <br/>
email without BBCode: <EMAIL email="email@for.you">email@for.you</EMAIL><br/>
<B><s>[b]</s>bold text<e>[/b]</e></B><br/>
<I><s>[i]</s>italic text<e>[/i]</e></I><br/>
<U><s>[u]</s>underlined text<e>[/u]</e></U><br/>
HTML-tags: &lt;strike&gt;Has no effect&lt;/strike&gt;
<CODE><s>[code]</s>A piece of code<e>[/code]</e></CODE>
<IMG src="https://upload.wikimedia.org/wikipedia/commons/4/4a/Dot-yellow.gif"><s>[img]</s><URL url="https://upload.wikimedia.org/wikipedia/commons/4/4a/Dot-yellow.gif"><LINK_TEXT text="https://upload.wikimedia.org/wikipedia/ ... yellow.gif">https://upload.wikimedia.org/wikipedia/commons/4/4a/Dot-yellow.gif</LINK_TEXT></URL><e>[/img]</e></IMG> <br/>
Image with relative link1: <IMG src="path/to/image/image.jpg"><s>[img]</s>./styles/moschistyle32/theme/images/Moschifreunde.jpg<e>[/img]</e></IMG><br/>
Image with relative link2: <IMG src="/path/to/image/image.jpg"><s>[img]</s>./styles/moschistyle32/theme/images/Moschifreunde.jpg<e>[/img]</e></IMG><br/>
[attachment]an attachment[/attachment] <br/>
<COLOR color="red"><s>[color=red]</s>Red color<e>[/color]</e></COLOR><br/>
<SIZE size="110"><s>[size=110]</s>A bit bigger<e>[/size]</e></SIZE>
<LIST><s>[list]</s><LI>Start of List</LI>
<LI><s>[*]</s>first list item</LI>
<e>[/list]</e></LIST></r>
EOD;
		$expected = <<<EOD
mention -&gt; [quote]Quote something[/quote]
bot_command -&gt; /should not be treated as command 
url1 -&gt; <a href="http://google.com">BBCode-Url with text</a>
url2 -&gt; <a href="http://google.com">http://google.com</a> (BBCode-Url without text)
url3 without bbcode: <a href="http://google.com">http://google.com</a> 
email -&gt; email@for.you (Email in BBCode) 
email without BBCode: email@for.you
<B>bold text</B>
<I>italic text</I>
<U>underlined text</U>
HTML-tags: &lt;strike&gt;Has no effect&lt;/strike&gt;
<CODE>[code]A piece of code[/code]</CODE>
<a href="https://upload.wikimedia.org/wikipedia/commons/4/4a/Dot-yellow.gif">&lt;&lt;IMAGE&gt;&gt;</a> 
Image with relative link1: <a href="http://server.name/phpbb/path/to/image/image.jpg">&lt;&lt;IMAGE&gt;&gt;</a>
Image with relative link2: <a href="http://server.name/path/to/image/image.jpg">&lt;&lt;IMAGE&gt;&gt;</a>
[attachment]an attachment[/attachment] 
[color=red]Red color[/color]
[size=110]A bit bigger[/size]
[list]Start of List
[*]first list item
[/list]
EOD;
		$formatted = $this->formatters->format_post_for_telegram($input);
		$this->assertEquals($expected, $formatted);
	}

	/** Test the formatting of unknown (custom) BBCodes with enclosed urls.
	 */
	public function test_format_bbcode_with_url()
	{
		//Function generate_board_url called in formatters->format_post_for_telegram
		//expects some globals to be set.
		global $config, $user;
		$config = $this->config;
		$user = $this->user;

		$input = '[my_bbcode=option]<URL url="http://google.com">http://google.com</URL>[/my_bbcode]';
		$expected = '<a href="http://google.com">&lt;&lt;my_bbcode&gt;&gt;</a>';
		$formatted = $this->formatters->format_post_for_telegram($input);
		$this->assertEquals($expected, $formatted);
	}

	public function test_parse_nested_tags()
	{
		$input = <<<'EOD'
url1 -&gt; <a href="http://google.com">BBCode-Url with text</a>
<B>bold <I>italic (self closing br <br/>)<U>underlined 
with umlauts: ÄÜÖäöüß<i>nested italic</i> text</U> text</I> text</B>
<CODE>[code]A piece of code[/code]</CODE>
EOD;

		$tag_info = $this->formatters->parse_tags($input);
		$expected = [
			'<a href="http://google.com">BBCode-Url with text</a>',
			"<B>bold <I>italic (self closing br <br/>)<U>underlined \nwith umlauts: ÄÜÖäöüß<i>nested italic</i> text</U> text</I> text</B>",
			'<CODE>[code]A piece of code[/code]</CODE>',
			"<I>italic (self closing br <br/>)<U>underlined \nwith umlauts: ÄÜÖäöüß<i>nested italic</i> text</U> text</I>",
			'<br/>',
			"<U>underlined \nwith umlauts: ÄÜÖäöüß<i>nested italic</i> text</U>",
			'<i>nested italic</i>',
		];
		$full_texts = array_column($tag_info, 'full');
		//$this->assertEquals('', print_r($tag_info, true)); //for output of $tag_info
		$this->assertEquals($expected, $full_texts);
	}

	public function pure_text_substr_data_provider() {
		return array (
			[ '1<a>2<b>3<c>4</c>5</b>6</a>', 3, '<a><b><c>4</c>5</b>6</a>'], 
			[ 'ä<a>ö<b>ü<c>Ä</c>Ö</b>Ü</a>', 3, '<a><b><c>Ä</c>Ö</b>Ü</a>'], 
			[ '<a>2<b>3<c>4</c>5</b>6<selfclosing/></a>', 3, '<a><b><c>4</c>5</b>6<selfclosing/></a>'], 
			[ '1<a>2<b>3<c>4</c>5</b>6</a>7', 3, '<a><b>5</b>6</a>7'], 
			[ '<a>2<b>3<c>4</c>5</b>6</a>7', 1, '7'], 
			[ '<a>2<b>3<c>4</c>5</b>6</a>', 1, '<a>6</a>'], 
			[ '<a>1</a>', 100, '<a>1</a>'], 
			[ '<a>1&amp;2</a>', 1, '<a>2</a>'], 
			[ '<a>1&amp;2</a>', 2, '<a>&amp;2</a>'], 
			[ '<a>1&amp;2</a>', 3, '<a>1&amp;2</a>'], 
			[ '12345', 4, '2345'], 
		);
	}

	/** @dataProvider pure_text_substr_data_provider */
	public function test_pure_text_substr($input, $length, $expected)
	{
		$output = $this->formatters->pure_text_substr($input, $length);
		$this->assertEquals($expected, $output);
	}

}
