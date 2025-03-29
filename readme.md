# WorldLine Receipt Encoder Documentation

Welcome to the WorldLine Receipt Encoder documentation repository. This software is designed to encode and decode receipt data for various payment processing scenarios, including refunds, reversals, captures, and more. Below you'll find a detailed explanation of how to use this software, along with important safety and usage guidelines.

## Table of Contents
1. **General Usage**
2. **Safety Notice**
3. **Code Structure**
4. **API Endpoints**
5. **Example Usage**
6. **Contributing**

## 1. General Usage
The WorldLine Receipt Encoder is a versatile tool that allows you to encode and decode receipt data for different payment transactions. The software provides endpoints to handle various operations such as refunds, reversals, captures, and more. Below are the general steps on how to use this software:

- **Action Handling**: Send an HTTP POST request to the appropriate endpoint with specific parameters based on the action you want to perform (e.g., `refund_c`, `reversal_m`).
- **Receipt Decoding**: Use the `decodeReceipt` endpoint to decode a previously encoded receipt.

## 2. Safety Notice
**Important:** By using this software, you acknowledge that:
- The development team does not assume any responsibility for any issues arising from the use of this code.
- You are solely responsible for testing and validating the functionality of this code in your specific environment.
- This software is provided "as is," without warranty of any kind.

## 3. Code Structure
The repository contains several PHP files that handle different functionalities:

- `action.php`: Main script that processes incoming requests and returns appropriate responses based on the action specified.
- `decode_receipt.php`: Functions to decode encoded receipt data.
- `functions.php`: Utility functions used throughout the application.

## 4. API Endpoints
The following are the main endpoints provided by this software:

### Encoding Receipts
- **`POST /action.php`**: Handles various actions such as refunds, reversals, captures, etc., and returns encoded receipt data.
 - **Parameters**: `action` (e.g., `refund_c`, `reversal_m`), additional parameters specific to the action.
 - **Response**: JSON object containing `escposContent`.

### Decoding Receipts
- **`POST /decodeReceipt.php`**: Decodes a previously encoded receipt.
 - **Parameters**: `encodedReceipt` (base64-encoded string of the receipt).
 - **Response**: Decoded receipt data in plain text or HTML format, depending on the implementation.

## 5. Example Usage
Here is an example of how to use the software:

### Encoding a Receipt
```bash
curl -X POST http://localhost/action.php \
    -H "Content-Type: application/json" \
    -d '{"action": "refund_c", "param1": "value1", "param2": "value2"}'
```

### Contributing
Feel free to fork this repository and modify the code according to your needs. If you have any improvements or bug fixes, please submit a pull request, and we'll review them accordingly.

Thank you for using the WorldLine Receipt Decoder! We hope this software meets all your requirements and provides a reliable solution for decoding WorldLine receipt data.