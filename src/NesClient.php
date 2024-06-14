<?php

namespace NES\client;

class NesClient
{
  protected $endpoint;
  private $_tmpToken = null;

  public function __construct($endpoint)
  {
    $this->endpoint = $endpoint;
  }

  public function getPackageAdvisories($products)
  {
    $input = [
      'tenantId' => 1000,
      'productIn' => is_array($products) ? $products : [$products],
      'reportTypeIn' => ['NES_PACKAGE_ADVISORY'],
    ];
    $res = $this->query(NesQueries::$INSIGHTS_REPORTS, ['insightInput' => $input]);

    // Handle the response
    $result = (object) $res['insights']['reports']['results'];
    // if (!$result->success) {
    //   throw new \Exception('Could not initialize reporter token: ' . $result->message);
    // }


    // return $result->oid;
    return $result;
  }

  /**
   * Request a token with the reporter details, with the only requirements being: 
   *  - Must include a 'id' property of type string.
   *  - Must JSON.stringify(..) to 256 characters or less.
   * 
   * This information will be encoded to the token (it's a signed JWT) but should 
   * not include sensitive or even very specific identifying information. 
   * 
   * This process is largely designed to prevent abuse and help  
   */
  public function getReporterToken($reporterDetails)
  {
    $input = [
      'context' => [
        'client' => $reporterDetails
      ]
    ];
    $res = $this->query(NesQueries::$REPORTER_INIT, ['initInput' => $input]);

    // Handle the response
    $result = (object) $res['telemetry']['initialize'];
    if (!$result->success) {
      throw new \Exception('Could not initialize reporter token: ' . $result->message);
    }

    return $result->oid;
  }

  public function sendReport($token, $report, $metadata = null)
  {
    $input = [
      'key' => 'd7:diagnostics:report',
      'report' => $report,
      'metadata' => $metadata
    ];

    $this->_tmpToken = $token;
    $res = $this->query(NesQueries::$REPORT_SUBMIT, ['reportInput' => $input]);
    $this->_tmpToken = null;

    // Handle the response
    $result = (object) $res['telemetry']['report'];
    if (!$result->success) {
      throw new \Exception('Report Submission Failed: ' . $result->message);
    }

    return (object) [
      'reportId' => $result->txId
    ];
  }

  protected function query($query, $variables = [])
  {
    $headers = [
      'Content-Type: application/json',
      'Accept: application/json',
    ];
    if (!is_null($this->_tmpToken)) {
      $headers[] = 'x-nes-telrep: ' . $this->_tmpToken;
    }

    $dataString = json_encode([
      'query' => $query,
      'variables' => $variables
    ]);

    $ch = curl_init($this->endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
      throw new \Exception("HTTP Error: " . $httpCode . " - Failed to receive proper response");
    }

    $response = json_decode($result, true);
    if (isset($response['errors'])) {
      throw new \Exception('GraphQL Error: ' . json_encode($response['errors']));
    }
    return $response['data'];
  }
}

class NesQueries
{
  static $REPORTER_INIT = <<<EOL
    mutation Telemetry(\$initInput: TelemetryInitInput!){
        telemetry {
            initialize(input: \$initInput) {
                success
                oid
                message
                diagnostics
            }
        }
    }
    EOL;

  static $REPORT_SUBMIT = <<<EOL
    mutation Report(\$reportInput: TelemetryReportInput!){
        telemetry {
            report(input: \$reportInput) {
                txId
                success
                message
                diagnostics
            }
        }
    }
    EOL;

  static $INSIGHTS_REPORTS = <<<EOL
    query insightReports(\$insightInput: InsightsPackageReportSearchInput!) {
      insights {
        reports(input: \$insightInput) {
          results {
            id 
            key
            name
            type
            affectedPackages {
              fqns
            }
            entries {
              id
              key
              message
              metadata
              ordinal
              type
            }
          }
        }
      }
    }
    EOL;
}
