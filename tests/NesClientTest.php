<?php

require_once (dirname(__FILE__) . "/../src/NesDrupal.php");

use PHPUnit\Framework\TestCase;

class NesClientTest extends TestCase
{

  private $client;

  public function __construct($arg)
  {
    parent::__construct($arg);
    // https://api.dev.nes.herodevs.com/graphql
    $this->client = new NES\client\NesClient('http://localhost:3000/graphql');
  }
  public function testReport()
  {
    $token = $this->client->getReporterToken(['id' => 'foobarbaz']);
    $result = $this->client->sendReport($token, (object) [
      'some_data' => 123
    ], ['some_meta' => 'blah']);

    $this->assertNotEmpty($result);
    $this->assertNotEmpty($result->reportId);
  }

  public function testUnknownModule()
  {
    $reports = $this->client->getPackageAdvisories(['drupal_7']);
    $this->assertNotEmpty($reports);

    $oracle = new NES\drupal\NesDrupalOracle($reports);
    $report = $oracle->getReport('foobarbaz');
    $this->assertNull($report);
  }

  // public function testInsights()
  // {
  //   $reports = $this->client->getPackageAdvisories(['drupal_7']);
  //   $this->assertNotEmpty($reports);

  //   $oracle = new NES\drupal\NesDrupalOracle($reports);


  //   // get the reports for all modules
  //   $reports = array_map(function ($module) use ($oracle) {
  //     return $oracle->getReport($module);
  //   }, $oracle->modules);

  //   // sample the data
  //   error_log(print_r(json_encode($reports), true));
  // }

  public function testClassExists()
  {
    $this->assertTrue(class_exists(NES\client\NesClient::class), "NesClient class does not exist");
  }
}
