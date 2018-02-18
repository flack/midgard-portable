<?php
/**
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

namespace midgard\portable\storage\type;

use midgard_datetime;
use DateTime as date_base;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

class datetime extends DateTimeType
{
    const TYPE = 'midgard_datetime';

    /**
     * @param string $value
     * @param AbstractPlatform $platform
     * @return DateTime|mixed|null
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return new midgard_datetime;
        }
        $val = date_base::createFromFormat($platform->getDateTimeFormatString(), $value);
        if (!$val) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
        return new midgard_datetime($val->format('Y-m-d H:i:s'));
    }
}
