# Demo of use of the zec data parsing library

## Primitive Validation

### Basic type validation
```php
// Validation primitive type
use Zec\z;

$stringSchema = z::string();
$numberSchema = z::number();
$booleanSchema = z::boolean();

// validate values
try {
    $stringSchema->validate("Hello"); // true
    $stringSchema->validate(1); // string error message expected "The value must be a string"
} catch (Zec\Exception\ZecException $e) {
    echo $e->getMessage();

    $error = $e->getErrors();
    // $error[0]['message'] = "The value must be a string"
    // $error[0]['path'] = []
    // $error[0]['value'] = 1
}
```

### Enhanced Primitive validation with constraints

```php
// Enhanced primitive validation with constraints
use Zec\z;

$userNameSchema = z::string()
    ->min(3, "Username must be at least 3 characters long")
    ->max(20, "Username must be less than 20 characters long")
    ->pattern("/^[a-zA-Z0-9]+$/", "Username must contain only letters and numbers");

$ageSchema = z::number()
    ->integer("Age must be an integer")
    ->min(18, "You must be at least 18 years old")
    ->max(100, "You must be less than 100 years old")

$roleSchema = z::string()
    ->oneOf(["admin", "user", "guest"], "Invalid role")
    ->default("guest");

$result = $userNameSchema->safeValidate("JohnDoe123"); // true
if ($result->success) {
    echo "Username is valid: " . $result->data;
} else {
    echo "Username is invalid: " . $result->error;
}

$numberFromStringSchema = z::number()
    ->coerce() // will convert string to number

$value = $numberFromStringSchema->safeValidate("123"); // true
if ($value->success) {
    echo "Value is valid: " . $value->data;
} else {
    echo "Value is invalid: " . $value->error;
}
```

## Object/Entity Validation

### Simple Object Validation

```php
use Zec\z;

$userSchema = z::object([
    "id" => z::number(),
    "name" => z::string()->min(3)->max(50),
    "email" => z::string()->email("Invalid email address"),
    "age" => z::number()->integer("Age must be an integer"),
    "createdAt" => z::date()->format("Y-m-d H:i:s")
]);

try {
    $user = $userSchema->safeValidate([
        "id" => 1,
        "name" => "John Doe",
        "email" => "john.doe@example.com",
        "age" => 30,
        "createdAt" => "2023-01-01 12:00:00"
    ]);
} catch (\Zec\Exception\ZecException $e) {
    $errors = $e->getErrors();
    foreach ($errors as $error) {
        echo $error['message'] . "\n";
        echo $error['path'] . "\n";
        echo $error['value'] . "\n";
    }
}
```

### Advanced Object Validation with custom Logic

```php
use Zec\z;

$addressSchema = z::object([
    'street' => Schema::string(),
    'city' => Schema::string(),
    'state' => Schema::string()->length(2)->uppercase(),
    'zipCode' => Schema::string()->pattern('/^\d{5}(-\d{4})?$/')
]);

$productSchema = z::object([
    'id' => z::number(),
    'name' => z::string()->min(3)->max(100),
    'price' => z::number()->min(0),
    'tags' => Schema::array(Schema::string()),
    'inStock' => Schema::boolean()
])->refine(
    function ($data) {
        if ($data['inStock'] && empty($data['tags'])) {
            return "Products must have at least one tag if they are in stock";
        }
        return true;
    }, "Products must have at least one tag if they are in stock");
```

## Relational/Cross-entity Validation

### Basic Cross-entity Validation

```php
use Zec\z;

$categorySchema = z::object([
    'id' => z::string()->uuid(),
    'name' => z::string()->min(3)->max(100),
    'parentId' => z::string()->uuid()->nullable()
]);

$productSchema = z::object([
    'id' => Schema::string()->uuid(),
    'name' => Schema::string(),
    'price' => Schema::number()->positive(),
    'categoryId' => Schema::string()->uuid()
]);

$validationProductCategory = z::refinement()
    ->input(z::tuple([$productSchema, $categorySchema]  ))
    ->predicate(function($data) {
        [$product, $categories] = $data;
        
        // Check if product's category exists in categories array
        foreach ($categories as $category) {
            if ($category['id'] === $product['categoryId']) {
                return true;
            }
        }
        return false;
    }, "Product must reference an existing category");


// Usage
try {
    $validationProductCategory->validate([
        [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Laptop',
            'price' => 999.99,
            'categoryId' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8'
        ],
        [
            [
                'id' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
                'name' => 'Electronics',
                'parentId' => null
            ]
        ]
    ]);

    // Valid: the product categoryId matches an existing category
} catch (\Zec\Exception\ZecException $e) {
    // Handle validation errors
}  
```

### Complex Relational Validation

```php
use Zec\z;

// Order schema with line items
$lineItemSchema = z::object([
    'productId' => z::string()->uuid(),
    'quantity' => z::number()->int()->positive(),
    'unitPrice' => z::number()->positive()
]);

$orderSchema = z::object([
    'id' => z::string()->uuid(),
    'customerId' => z::string()->uuid(),
    'items' => z::array($lineItemSchema)->min(1, 'Order must have at least one item'),
    'totalAmount' => z::number()->positive(),
    'status' => z::enum(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
]);


// Cross validation of order total and line items
$validateOrderTotal = Schema::object([
    'order' => $orderSchema,
    'inventory' => Schema::record(Schema::string(), Schema::number()->int())
])
->refine(
    function($data) {
        $order = $data['order'];
        $inventory = $data['inventory'];
        
        // Validate total matches sum of line items
        $calculatedTotal = 0;
        foreach ($order['items'] as $item) {
            $calculatedTotal += $item['quantity'] * $item['unitPrice'];
        }
        
        return abs($calculatedTotal - $order['totalAmount']) < 0.01;
    },
    'Order total must match the sum of all line items'
)
->refine(
    function($data) {
        $order = $data['order'];
        $inventory = $data['inventory'];
        
        // Check inventory levels
        foreach ($order['items'] as $item) {
            $productId = $item['productId'];
            if (!isset($inventory[$productId]) || $inventory[$productId] < $item['quantity']) {
                return false;
            }
        }
        
        return true;
    },
    'Insufficient inventory for one or more items'
);

```