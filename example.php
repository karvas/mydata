<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $series = $_POST['series'];
  $aa = $_POST['aa'];
  $issue_date = $_POST['issue_date'];
  $vat_number = $_POST['vat_number'];
  $net_value = $_POST['net_value'];
  $vat_amount = $_POST['vat_amount'];
  $tax_amount = $_POST['tax_amount'];
  $counterpart_vat = $_POST['counterpart_vat'] ?? ''; // Optional customer VAT
  $counterpart_name = $_POST['counterpart_name'] ?? 'Individual'; // Customer name

  // Calculate totals
  $total_net = (float)$net_value + (float)$tax_amount;
  $total_vat = (float)$vat_amount;
  $total_gross = $total_net + $total_vat;

  // Construct the payload
  $payload = [
    "invoices" => [
      [
        "invoice" => [
          "issuer" => [
            "vatNumber" => $vat_number,
            "country" => "GR",
            "branch" => 0 // Adjust if the issuer has multiple branches
          ],
          "counterpart" => [
            "vatNumber" => $counterpart_vat ?: null, // Null for B2C
            "country" => "GR", // Adjust based on customer
            "branch" => 0,
            "name" => $counterpart_name,
            "address" => [
              "street" => "", // Optional for B2C
              "number" => "",
              "postalCode" => "",
              "city" => ""
            ]
          ],
          "invoiceHeader" => [
            "series" => $series,
            "aa" => (int)$aa,
            "issueDate" => $issue_date,
            "invoiceType" => "1.1", // Tax Invoice (use "2.1" for Simplified Invoice)
            "currency" => "EUR"
          ],
          "paymentMethods" => [
            [
              "type" => 3, // Cash (adjust as needed: 1=Card, 2=Check, etc.)
              "amount" => $total_gross
            ]
          ],
          "invoiceDetails" => [
            [
              "lineNumber" => 1,
              "netValue" => (float)$net_value,
              "vatCategory" => 2, // 13% VAT for accommodation
              "vatAmount" => (float)$vat_amount,
              "incomeClassification" => [
                [
                  "classificationType" => "E3_561_001", // Accommodation services
                  "classificationCategory" => "category1_1", // Income from services
                  "amount" => (float)$net_value
                ]
              ]
            ],
            [
              "lineNumber" => 2,
              "netValue" => (float)$tax_amount,
              "vatCategory" => 7, // 0% VAT for taxes
              "vatAmount" => 0.0,
              "incomeClassification" => [
                [
                  "classificationType" => "E3_561_005", // Other taxes (Accommodation Tax)
                  "classificationCategory" => "category1_5", // Other income
                  "amount" => (float)$tax_amount
                ]
              ]
            ]
          ],
          "invoiceSummary" => [
            "totalNetValue" => $total_net,
            "totalVatAmount" => $total_vat,
            "totalWithheldAmount" => 0.0,
            "totalFeesAmount" => 0.0,
            "totalStampDutyAmount" => 0.0,
            "totalOtherTaxesAmount" => (float)$tax_amount,
            "totalDeductionsAmount" => 0.0,
            "totalGrossValue" => $total_gross,
            "incomeClassification" => [
              [
                "classificationType" => "E3_561_001",
                "classificationCategory" => "category1_1",
                "amount" => (float)$net_value
              ],
              [
                "classificationType" => "E3_561_005",
                "classificationCategory" => "category1_5",
                "amount" => (float)$tax_amount
              ]
            ]
          ]
        ]
      ]
    ]
  ];

  $client = new Client();
  $url = "https://mydataapidev.aade.gr/SendInvoices";

  try {
    $response = $client->post($url, [
      'headers' => [
        'Content-Type' => 'application/json',
        'aade-user-id' => 'vasiliskarantousis', // Replace with real user_id
        'Ocp-Apim-Subscription-Key' => 'a5d9b581753c951d5941bcb5239e869f' // Replace with real key
      ],
      'json' => $payload
    ]);

    $response_data = json_decode($response->getBody(), true);
    if (isset($response_data['response'][0]['invoiceMark'])) {
      echo "<p style='color: green;'>Success! Invoice MARK: " . $response_data['response'][0]['invoiceMark'] . "</p>";
    } else {
      echo "<p style='color: red;'>Success, but unexpected response format.</p>";
      echo "<pre>" . print_r($response_data, true) . "</pre>";
    }
  } catch (RequestException $e) {
    // Get the response body, if available
    $error_message = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
    $error_data = json_decode($error_message, true) ?: ['message' => $error_message, 'errors' => []];

    // Display the error details
    echo "<p style='color: red;'>Error: " . ($error_data['message'] ?? 'Unknown error') . "</p>";
    echo "<p>HTTP Status Code: " . ($e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A') . "</p>";
    if (!empty($error_data['errors'])) {
      echo "<h3>Detailed Errors:</h3>";
      echo "<pre>" . print_r($error_data['errors'], true) . "</pre>";
    } else {
      echo "<p>No detailed errors provided by the API.</p>";
      echo "<pre>Raw Error Response: " . $error_message . "</pre>";
    }

    // Log the error to a file for debugging
    $log_message = date('Y-m-d H:i:s') . " - Error: " . $error_message . "\n";
    $log_message .= "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    $log_message .= "Detailed Errors: " . print_r($error_data['errors'], true) . "\n";
    file_put_contents('mydatapi_errors.log', $log_message, FILE_APPEND);
  } catch (GuzzleException $e) {
    // Handle general Guzzle exceptions (e.g., network issues)
    echo "<p style='color: red;'>General Error: " . $e->getMessage() . "</p>";

    // Log the general error
    $log_message = date('Y-m-d H:i:s') . " - General Error: " . $e->getMessage() . "\n";
    $log_message .= "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    file_put_contents('mydatapi_errors.log', $log_message, FILE_APPEND);
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Accommodation Invoice</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; }
        label { display: block; margin: 10px 0 5px; }
        input { width: 100%; padding: 8px; margin-bottom: 10px; }
        button { padding: 10px 20px; background: #007BFF; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        p { font-weight: bold; }
    </style>
</head>
<body>
<h2>Issue Accommodation Invoice</h2>
<form method="post" action="">
    <label for="series">Series:</label>
    <input type="text" id="series" name="series" value="A" required>

    <label for="aa">Invoice Number (AA):</label>
    <input type="number" id="aa" name="aa" value="1" required>

    <label for="issue_date">Issue Date:</label>
    <input type="date" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>

    <label for="vat_number">Issuer VAT Number:</label>
    <input type="text" id="vat_number" name="vat_number" placeholder="123456789" required>

    <label for="counterpart_name">Customer Name (Optional):</label>
    <input type="text" id="counterpart_name" name="counterpart_name" placeholder="Individual">

    <label for="counterpart_vat">Customer VAT Number (Optional):</label>
    <input type="text" id="counterpart_vat" name="counterpart_vat" placeholder="Leave blank for individuals">

    <label for="net_value">Net Value (Accommodation):</label>
    <input type="number" step="0.01" id="net_value" name="net_value" value="100.00" required>

    <label for="vat_amount">VAT Amount (13%):</label>
    <input type="number" step="0.01" id="vat_amount" name="vat_amount" value="13.00" required>

    <label for="tax_amount">Accommodation Tax:</label>
    <input type="number" step="0.01" id="tax_amount" name="tax_amount" value="4.00" required>

    <button type="submit">Submit Invoice</button>
</form>
</body>
</html>