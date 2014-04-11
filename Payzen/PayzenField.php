<?php
namespace Payzen\Payzen;

/**
 * Class representing a field of the form to send to the payment gateway
 */
class PayzenField {
    /**
     * Field's name. Matches the html input attribute
     * @var string
     * @access private
     */
    var $name;
    /**
     * Field's label in english, to be used by translation systems
     * @var string
     * @access private
     */
    var $label;
    /**
     * Field's maximum length. Matches the html text input attribute
     * @var int
     * @access private
     */
    var $length;
    /**
     * PCRE regular expression the field value must match
     * @var string
     * @access private
     */
    var $regex;
    /**
     * Whether the form requires the field to be set (even to an empty string)
     * @var boolean
     * @access private
     */
    var $required;
    /**
     * Field's value. Null or string
     * @var string
     * @access private
     */
    var $value = null;

    /**
     * Constructor
     * @param string $name
     * @param string $label
     * @param string $regex
     * @param boolean $required
     * @param string $value
     * @return PayzenField
     */
    function __construct($name, $label, $regex, $required = false, $length = 255) {
        $this->name = $name;
        $this->label = $label;
        $this->regex = $regex;
        $this->required = $required;
        $this->length = $length;
    }

    /**
     * Setter for value
     * @param mixed $value
     * @return boolean true if the value is valid
     */
    function setValue($value) {
        $value = ($value === null) ? null : (string) $value;
        // We save value even if invalid (in case the validate function is too restrictive, it happened once) ...
        $this->value = $value;
        if (!$this->validate($value)) {
            // ... but we return a "false" warning
            return false;
        }
        return true;
    }

    /**
     * Checks the current value
     * @return boolean false if the current value is invalid or null and required
     */
    function isValid() {
        return $this->validate($this->value);
    }

    /**
     * Check if a value is valid for this field
     * @param string $value
     * @return boolean
     */
    function validate($value) {
        if ($value === null && $this->isRequired()) {
            return false;
        }
        if ($value !== null && !preg_match($this->regex, $value)) {
            return false;
        }
        return true;
    }

    /**
     * Setter for the required attribute
     * @param boolean $required
     */
    function setRequired($required) {
        $this->required = (boolean) $required;
    }

    /**
     * Is the field required in the payment request ?
     * @return boolean
     */
    function isRequired() {
        return $this->required;
    }

    /**
     * Return the current value of the field.
     * @return string
     */
    function getValue() {
        return $this->value;
    }

    /**
     * Return the name (html attribute) of the field.
     * @return string
     */
    function getName() {
        return $this->name;
    }

    /**
     * Return the english human-readable name of the field.
     * @return string
     */
    function getLabel() {
        return $this->label;
    }

    /**
     * Return the maximum length of the field's value.
     * @return number
     */
    function getLength() {
        return $this->length;
    }

    /**
     * Has a value been set ?
     * @return boolean
     */
    function isFilled() {
        return !is_null($this->getValue());
    }
}
