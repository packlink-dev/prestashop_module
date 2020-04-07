<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Packlink\BusinessLogic\DTO\ValidationError;

/**
 * Class PacklinkPrestaShopUtility
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class PacklinkPrestaShopUtility
{
    /**
     * Translation messages for fields that are being validated.
     *
     * @var array
     */
    private static $validationMessages = array(
        'email' => 'Field must be valid email.',
        'phone' => 'Field must be valid phone number.',
        'weight' => 'Weight must be a positive decimal number.',
        'postal_code' => 'Postal code is not correct.',
    );

    /**
     * Returns invalid JSON response with validation errors.
     *
     * @param ValidationError[] $errors
     */
    public static function die400WithValidationErrors($errors)
    {
        $result = array();

        foreach ($errors as $error) {
            $result[$error->field] = self::getValidationMessage($error->code, $error->field);
        }

        self::die400($result);
    }

    /**
     * Die with 400 status in header.
     *
     * @param array $data
     */
    public static function die400(array $data = array())
    {
        header('HTTP/1.1 400 Bad Request');

        self::dieJson($data);
    }

    /**
     * Die with 404 status in header.
     *
     * @param array $data
     */
    public static function die404(array $data = array())
    {
        header('HTTP/1.1 404 Not Found');

        self::dieJson($data);
    }

    /**
     * Die with 404 status in header.
     *
     * @param array $data
     */
    public static function die500(array $data = array())
    {
        header('HTTP/1.1 500 Internal Server Error');

        self::dieJson($data);
    }

    /**
     * Converts front DTOs to array and returns a JSON response.
     *
     * @param \Packlink\BusinessLogic\DTO\BaseDto[] $entities
     */
    public static function dieDtoEntities(array $entities)
    {
        $result = array();

        foreach ($entities as $entity) {
            $result[] = $entity->toArray();
        }

        self::dieJson($result);
    }

    /**
     * Sets response header content type to json, echos supplied $data as json and terminates the process.
     *
     * @param array $data Array to be encoded to json response.
     */
    public static function dieJson(array $data = array())
    {
        header('Content-Type: application/json');

        die(json_encode($data));
    }

    /**
     * Sets response header content plaintext, echos $plainText and terminates the process.
     *
     * @param string $plainText
     */
    public static function diePlain($plainText = '')
    {
        header('Content-Type: text/plain');

        die($plainText);
    }

    /**
     * Sets file specified by $filePath as response.
     *
     * @param string $filePath
     * @param string $outputFileName
     */
    public static function dieFile($filePath, $outputFileName = '')
    {
        $fileName = $outputFileName !== '' ? $outputFileName : basename($filePath);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);

        die(200);
    }

    /**
     * Sets string specified by $content as a file response.
     *
     * @param string $content
     * @param string $fileName
     */
    public static function dieFileFromString($content, $fileName)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . \Tools::strlen($content));

        echo $content;

        die(200);
    }

    /**
     * Returns file inline.
     *
     * @param string $filePath
     * @param string $outputFileName
     */
    public static function dieInline($filePath, $outputFileName = '')
    {
        $fileName = $outputFileName !== '' ? $outputFileName : basename($filePath);

        header('Content-Type: ' . mime_content_type($filePath));
        header('Content-Disposition: inline; filename=' . $fileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);

        die(200);
    }

    /**
     * Retrieves post input.
     *
     * @return array
     */
    public static function getPacklinkPostData()
    {
        return json_decode(\Tools::getValue('plPostData'), true);
    }

    /**
     * Returns a validation message for validation error.
     *
     * @param string $code
     * @param string $field
     *
     * @return string
     */
    protected static function getValidationMessage($code, $field)
    {
        if ($code === ValidationError::ERROR_REQUIRED_FIELD) {
            return TranslationUtility::__('Field is required.');
        }

        if (in_array($field, array('height', 'length', 'width'), true)) {
            return TranslationUtility::__('Field must be valid number.');
        }

        return TranslationUtility::__(self::$validationMessages[$field]);
    }
}
