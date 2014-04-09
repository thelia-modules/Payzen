<?php
namespace Payzen\Model;

use Payzen\Model\Base\PayzenConfigQuery as BasePayzenConfigQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'payzen_config' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class PayzenConfigQuery extends BasePayzenConfigQuery
{
    /**
     * Return a configuration variable value, or a default value if the variable was not found
     *
     * @param string $name the configuration variable name
     * @param string $default the default value
     * @return string the value
     */
    public static function read($name, $default = null)
    {
        $value = self::create()->findOneByName($name);

        return $value ? $value->getValue() : $default;
    }

    /**
     * Set or update a configuration variable value.
     *
     * @param string $name the configuration variable name
     * @param string $value the configuration value
     */
    public static function set($name, $value)
    {
        $config = self::create()->findOneByName($name);

        if (null == $config) {
            $config = new PayzenConfig();

            $config->setName($name);
        }

        $config->setValue($value)->save();
    }
}
