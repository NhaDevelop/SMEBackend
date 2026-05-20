<?php
$answerDataValue = [["label" => "100%", "points" => 20]];

$extractedValue = is_array($answerDataValue)
    ? ($answerDataValue['label'] ?? $answerDataValue['value'] ?? (
        isset($answerDataValue[0]) && is_array($answerDataValue[0]) 
            ? ($answerDataValue[0]['label'] ?? $answerDataValue[0]['value'] ?? json_encode($answerDataValue))
            : json_encode($answerDataValue)
    ))
    : $answerDataValue;

echo "Extracted: " . $extractedValue . "\n";
