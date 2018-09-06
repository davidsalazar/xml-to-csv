<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DOMDocument, DOMXpath;

class Xml2csv extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'create:csv {xmlfile} {--r|row=} {--c|column=*} {--debug}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Creates CSV from an XML file. \nBoth row and column accept an xpath query. \nrow will be your context when column is used. \ncolumn can also leverage methods using -> operator within the w3 node interface at https://bit.ly/2wB4BPH\nEx. create:csv file.xml --row=\"//product\" --column=\"->getAttribute('product-id')\" --column=name --column=\"page-attributes/page-url\" --column=\"sitemap-priority->getAttribute('site-id')\"";

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$xmlfile = $this->argument('xmlfile');
		$row = $this->option('row');
		$columns = $this->option('column');

		if (!is_readable($file = $origFile = storage_path("private/$xmlfile")))
			return $this->error("Could not find file \"$xmlfile\"");

		if (!$row || strlen($row) < 3)
			return $this->error("--row= is a required option.\nPlease use an xpath selector to determine the row for every record.\nEx. --row=//product");

		// we are stripping out all namesapces in xml file until i can get this to work with namespaces. 
		if (!is_readable($file = storage_path("private/" . md5_file($file) . '.xml'))) {
			$this->debug('Stripping out namespaces from xml');
			exec("sed -E 's/ xmlns(:[^=]+)?=\"[^\"]*\"//' $origFile > $file");
			if (!is_readable($file))
				return $this->error("Could not find file \"$file\". This was probably due to a namespace issue.");
		}

		$this->debug('Start');
		$writeFile = "$xmlfile.csv";
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->load($file);
		$this->debug('Loading File');
		$xpath = new DOMXpath($doc);

		// $ns = $doc->documentElement->namespaceURI;
		// if ($ns)
			// $xpath->registerNamespace("ns", $ns);

		$this->debug('Load DOMXpath');
		//$result = $xpath->query($ns ? str_replace('//', '//ns:', $row) : $row);
		$result = $xpath->query($row);
		$this->debug('Load Query');
		if (!($rowCount = $result->length))
			return $this->error("row xpath query returned 0 results for \"$row\"");
		else
			$this->info(number_format($rowCount) . " records found for \"$row\", path at " . substr($result[0]->getNodePath(), 0, -3));

		$validColumns = [];
		foreach ($columns as $columnSelector)
		{
			$prop = $func = $arg = null;
			// check to see if there is a method or prop attached to selector
			@list($selector, $method) = explode('->', $columnSelector);
			// if we know there is a method or prop, then we have to parse through it to determine if its a prop or method with arg
			if ($method) {
				@list($func, $arg) = explode('(', $method);
				if ($func && !$arg)
					$prop = $func;
				else
					$arg = trim(substr($arg, 0, -1), '"\'');
			}

			// columnSelector is original selector, selector is without method if exists, prop / method / arg are only populated if they exist
			$validColumns[] = ['columnSelector' => $columnSelector, 'selector' => $selector, 'prop' => $prop, 'method' => $func, 'arg' => $arg];
		}



		if ($result->length)
			$bar = $this->output->createProgressBar(ceil($result->length / 1000));
		$i = 0;
		$fp = fopen(storage_path("private/$writeFile"), 'w');
		foreach ($result as $v)
		{
			$row = [];
			if ($columns)
			{
				foreach ($validColumns as $validColumn)
				{
					if ($validColumn['selector'] === '' && $validColumn['method']) {
						$row[$v->getNodePath() . $validColumn['columnSelector']] = $validColumn['prop'] ? $v->$validColumn['prop'] : $v->$validColumn['method']($validColumn['arg']);
					}
					else {
						// query column selector using the record context
						$columnResults = @$xpath->query($validColumn['selector'], $v);

						$k = $v->getNodePath() . '/' . $validColumn['columnSelector'];

						if (!$columnResults || $columnResults->length == 0) {
							$row[$k] = '';
							$this->error("column xpath query returned 0 results for \"{$validColumn['selector']}\"");
						}
						elseif ($validColumn['prop']) {
							$row[$k] = $columnResults->item(0)->$validColumn['prop'];
						}
						elseif ($validColumn['method']) {
							$row[$k] = $validColumn['arg'] ? $columnResults->item(0)->$validColumn['method']($validColumn['arg']) : $columnResults->item(0)->$validColumn['method']();
						}
						else {
							$row[$k] = $columnResults->item(0)->nodeValue;
						}
					}
				}
			}
			else
				$row = $this->getFlattendNodes($v);

			// writing header of csv
			if (++$i == 1)
				fputcsv($fp, array_keys($row));

			fputcsv($fp, $row);

			if ($i % 1000 == 0) {
				if (isset($bar))
					$bar->advance();
				$this->debug("Wrote $i records to csv");
			}
		}
		fclose($fp);
		$bar->advance();

		$this->info("\n" . number_format($i) . " records written to csv.");

		$this->info('Job Complete!');
	}


	private function debug($print)
	{
		if ($this->option('debug'))
			$this->line($print . ' ' . (memory_get_usage(true) / 1024 / 1024) . ' MB');
	}

	private function getFlattendNodes($node, $return = [])
	{
		if ($node->childNodes)
			foreach ($node->childNodes as $childNode)
				if ($childNode->childNodes && $childNode->childNodes->length)
					$return = $this->getFlattendNodes($childNode, $return);
				else
					if ($childNode->nodeValue || !$childNode->hasAttributes())
						$return[$childNode->getNodePath()] = $childNode->nodeValue;
					// if node value is empty and it has attributes, we try to use the first attribute instead
					elseif ($firstAttr = $childNode->attributes->item(0))
						$return[$childNode->getNodePath() . '@' . $firstAttr->nodeName] = $firstAttr->nodeValue;
		else
			$return[$node->getNodePath()] = $node->nodeValue;
		return $return;
	}

}