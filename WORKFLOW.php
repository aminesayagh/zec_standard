<?php

// Initialize the ZEC Validator
use Zec\z as z;

// Creating primitive validators
$stringValidator = z::string();
$numberValidator = z::number();
$booleanValidator = z::boolean();

// Creating validators with constraints
$userNameSchema = $stringValidator
    ->min(3, "Username must be at least 3 characters long")
    ->max(50, "Username must be less than 50 characters long");

// Creating object validator
$userSchema = z::object([
    "id" => z::number(),
    "name" => $userNameSchema,
    "email" => $stringValidator->email("Invalid email address"),
    "age" => $numberValidator->integer("Age must be an integer"),
    "isActive" => $booleanValidator
]);

// Refining schema with custom logic
$userSchema->refine(
    function ($data) {
        if ($data['age'] < 18) {
            return "User must be at least 18 years old";
        }
        return true;
    },
    "User must be at least 18 years old"
);

// Validating user data
$userData = [
    "id" => 1,
    "name" => "John Doe",
    "email" => "john.doe@example.com",
    "age" => 25,
    "isActive" => true
];

try {
    $validatedData = $userSchema->validate($userData);
    echo "Validation successful: " . json_encode($validatedData);
} catch (\Zec\Exception\ZecException $e) {
    echo "Validation failed: " . $e->getMessage();
}

