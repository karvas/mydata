<?php
require 'vendor/autoload.php';

use Firebed\AadeMyData\Http\MyDataRequest;
use Firebed\AadeMyData\Models\Invoice;
use Firebed\AadeMyData\Models\InvoiceHeader;
use Firebed\AadeMyData\Models\InvoiceDetails;
use Firebed\AadeMyData\Models\InvoiceSummary;
use Firebed\AadeMyData\Models\Issuer;
use Firebed\AadeMyData\Models\Counterpart;
use Firebed\AadeMyData\Models\IncomeClassification;
use Firebed\AadeMyData\Exceptions\MyDataException;
use Firebed\AadeMyData\Exceptions\MyDataAuthenticationException;
use Firebed\AadeMyData\Exceptions\MyDataConnectionException;
use Firebed\AadeMyData\Http\SendInvoices;

$myDataUserId = 'your_username';
$myDataSubscriptionKey = 'your_subscription_key';
$environment = 'dev';

MyDataRequest::setEnvironment($environment);
MyDataRequest::setCredentials($myDataUserId, $myDataSubscriptionKey);

MyDataRequest::verifyClient(false);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $issuerVat = filter_input(INPUT_POST, 'issuer_vat', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $issuerCountry = filter_input(INPUT_POST, 'issuer_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $issuerBranch = filter_input(INPUT_POST, 'issuer_branch', FILTER_SANITIZE_NUMBER_INT);
    $counterpartVat = filter_input(INPUT_POST, 'counterpart_vat', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $counterpartCountry = filter_input(INPUT_POST, 'counterpart_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $invoiceSeries = filter_input(INPUT_POST, 'invoice_series', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $invoiceAa = filter_input(INPUT_POST, 'invoice_aa', FILTER_SANITIZE_NUMBER_INT);
    $invoiceIssueDate = filter_input(INPUT_POST, 'invoice_issue_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $invoiceType = filter_input(INPUT_POST, 'invoice_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $lineNetValue = filter_input(INPUT_POST, 'line_net_value', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $lineVatCategory = filter_input(INPUT_POST, 'line_vat_category', FILTER_SANITIZE_NUMBER_INT);
    $lineVatAmount = filter_input(INPUT_POST, 'line_vat_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $incomeClassificationType = filter_input(INPUT_POST, 'income_classification_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $incomeClassificationCategory = filter_input(INPUT_POST, 'income_classification_category', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $totalNetValue = filter_input(INPUT_POST, 'total_net_value', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $totalVatAmount = filter_input(INPUT_POST, 'total_vat_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $totalGrossValue = filter_input(INPUT_POST, 'total_gross_value', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    try {
        $invoice = new Invoice();

        $issuer = new Issuer();
        $issuer->setVatNumber($issuerVat);
        $issuer->setCountry($issuerCountry);
        $issuer->setBranch((int)$issuerBranch);

        $counterpart = new Counterpart();
        $counterpart->setVatNumber($counterpartVat);
        $counterpart->setCountry($counterpartCountry);

        $header = new InvoiceHeader();
        $header->setSeries($invoiceSeries);
        $header->setAa($invoiceAa);
        $header->setIssueDate($invoiceIssueDate);
        $header->setInvoiceType($invoiceType);
        $header->setCurrency($currency);

        $invoice->setIssuer($issuer);
        $invoice->setCounterpart($counterpart);
        $invoice->setInvoiceHeader($header);

        $invoiceLine = new InvoiceDetails();
        $invoiceLine->setLineNumber(1);
        $invoiceLine->setNetValue((float)$lineNetValue);
        $invoiceLine->setVatCategory((int)$lineVatCategory);
        $invoiceLine->setVatAmount((float)$lineVatAmount);

        if (!empty($incomeClassificationType) && !empty($incomeClassificationCategory)) {
            $classification = new IncomeClassification();
            $classification->setClassificationType($incomeClassificationType);
            $classification->setClassificationCategory($incomeClassificationCategory);
            $classification->setAmount((float)$lineNetValue);
            $invoiceLine->addIncomeClassification($classification);
        }

        $invoice->addInvoiceDetails($invoiceLine);

        $invoiceSummary = new InvoiceSummary();
        $invoiceSummary->setTotalNetValue((float)$totalNetValue);
        $invoiceSummary->setTotalVatAmount((float)$totalVatAmount);
        $invoiceSummary->setTotalGrossValue((float)$totalGrossValue);
        $invoice->setInvoiceSummary($invoiceSummary);

        $request = new SendInvoices();
        $response = $request->handle($invoice);

        if ($response->isSuccessful()) {
            $index = $response->getIndex();
            $uid = $response->getInvoiceUid();
            $mark = $response->getInvoiceMark();
            $cancelledByMark = $response->getCancellationMark();
            $qrUrl = $response->getQrUrl();
            print_r(compact('index', 'uid', 'mark', 'cancelledByMark', 'qrUrl'));
        } else {
            foreach ($response->getErrors() as $error) {
                $errors[$response->getIndex()][] = $error->getCode() . ': ' . $error->getMessage();
            }
        }

    } catch (MyDataConnectionException $connectionException) {
        echo "Error communicating with the server.";
    } catch (MyDataAuthenticationException $authenticationException) {
        echo "Authentication error, incorrect credentials.";
    } catch (MyDataException $myDataException) {
        echo $myDataException->getMessage();
    }
} else {
    echo "Invalid request method.";
}