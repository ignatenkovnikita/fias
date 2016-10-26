<?php

namespace ApiAction;

use AddressStorage;
use BadRequestException;
use Grace\DBAL\ConnectionAbstract\ConnectionInterface;

class AddressCompletion implements ApiActionInterface
{
    const BUILDING_ADDRESS_LEVEL = 0;

    /** @var ConnectionInterface */
    private $db;
    private $limit;
    private $address;
    private $parentId;
    private $maxAddressLevel;
    private $regions = [];

    public function __construct(ConnectionInterface $db, $address, $limit, $maxAddressLevel = 'building', array $regions = [])
    {
        $this->db      = $db;
        $this->limit   = $limit;
        $this->address = $address;
        $this->regions = $regions;

        if ($maxAddressLevel) {
            $this->maxAddressLevel = $this->getAddressLevelId($maxAddressLevel);
        }
    }

    public function run()
    {
        $storage      = new AddressStorage($this->db);
        $addressParts = static::splitAddress($this->address);

        $address        = $this->findAddressesWithoutParentId($addressParts['pattern']);//$storage->findAddress($addressParts['address']);

        $this->parentId = $address ? $address['address_id'] : null;
        $houseCount     = $address ? $address['house_count'] : null;


        if ($houseCount && $this->maxAddressLevel) {
            return [];
        }

        if ($this->getHouseCount()) {
            $rows = $this->findHouses($addressParts['pattern']);
            $rows = $this->setIsCompleteFlag($rows);
        } else {
            $rows = $this->findAddresses($addressParts['pattern']);
            $rows = $this->setIsCompleteFlag($rows);
        }

        foreach ($rows as $key => $devNull) {
            $rows[$key]['tags'] = ['address'];
        }

        return $rows;
    }

    private function getHouseCount()
    {
        if (!$this->parentId) {
            return null;
        }

        $sql = 'SELECT house_count FROM address_objects WHERE address_id = ?q';

        return $this->db->execute($sql, [$this->parentId])->fetchResult();
    }

    private function findAddressesWithParentId($pattern)
    {
        $where = $this->generateGeneralWherePart($pattern);
        $sql   = "
            SELECT full_title title, address_level, next_address_level
            FROM address_objects ao
            WHERE ?p
                AND (parent_id = ?q)
            ORDER BY ao.title
            LIMIT ?e"
        ;

        return $this->db->execute(
            $sql,
            [$where, $this->parentId, $this->limit]
        )->fetchAll();
    }

    private function findAddressesWithoutParentId($pattern)
    {

        $sql = "
            (
                SELECT id, address_id, full_title title, address_level, next_address_level
                FROM address_objects ao
                WHERE ?p:where:
                    AND (parent_id IS NULL)
                LIMIT ?e:limit:
            )
            UNION
            (
                SELECT ao.id, ao.address_id, ao.full_title title, ao.address_level, ao.next_address_level
                FROM address_objects ao
                INNER JOIN address_objects AS aop
                    ON aop.parent_id IS NULL
                        AND aop.address_id = ao.parent_id
                WHERE ?p:where:
                LIMIT ?e:limit:
            )
            ORDER BY title
            LIMIT ?e:limit:
            "
        ;

        $where  = $this->generateGeneralWherePart($pattern);
        $values = [
            'where' => $where,
            'limit' => $this->limit
        ];

        var_dump($this->db->execute($sql, $values)->fetchAll());


        return $this->db->execute($sql, $values)->fetchAll();
    }

    private function findAddresses($pattern)
    {
        if ($this->parentId) {
            return $this->findAddressesWithParentId($pattern);
        }

        return $this->findAddressesWithoutParentId($pattern);
    }

    private function generateGeneralWherePart($pattern)
    {
        $whereParts = [$this->db->replacePlaceholders("ao.title ilike '?e%'", [$pattern])];

        if ($this->maxAddressLevel) {
            $whereParts[] = $this->db->replacePlaceholders('ao.address_level <= ?q', [$this->maxAddressLevel]);
        }

        if ($this->regions) {
            $whereParts[] = $this->db->replacePlaceholders('ao.region IN (?l)', [$this->regions]);
        }

        return '(' . implode(') AND (', $whereParts) . ')';
    }

    private function findHouses($pattern)
    {
        $sql    = "
            SELECT full_title||', '||full_number title, ?q address_level, NULL next_address_level
            FROM houses h
            INNER JOIN address_objects ao
                ON ao.address_id = h.address_id
            WHERE h.address_id = ?q
                AND full_number ilike '?e%'
            ORDER BY (regexp_matches(full_number, '^[0-9]+', 'g'))[1]
            LIMIT ?e"
        ;
        $values = [static::BUILDING_ADDRESS_LEVEL, $this->parentId, $pattern, $this->limit];

        return $this->db->execute($sql, $values)->fetchAll();
    }

    private function setIsCompleteFlag(array $values)
    {
        foreach ($values as $key => $value) {
            $isMaxLevelReached       = $value['address_level'] == $this->maxAddressLevel;
            $doChildrenSuitNextLevel = ($value['next_address_level'] <= $this->maxAddressLevel)
                || (!$this->maxAddressLevel && !empty($value['house_count']))
            ;

            $values[$key]['is_complete'] = $isMaxLevelReached || !$doChildrenSuitNextLevel;

            unset($values[$key]['address_level']);
        }

        return $values;
    }

    private static function splitAddress($address)
    {
        $tmp = explode(',', $address);

        return [
            'pattern' => static::cleanAddressPart(array_pop($tmp)),
            'address' => implode(',', $tmp),
        ];
    }

    private static function cleanAddressPart($rawAddress)
    {
        // избавляемся от популярных префиксов/постфиксов (Вопросы по поводу регулярки к johnnywoo, сам я ее слабо понимаю).
        $cleanAddress = preg_replace('
            {
                (?<= ^ | [^а-яА-ЯЁё] )

                (?:ул|улица|снт|деревня|тер|пер|переулок|ал|аллея|линия|проезд|гск|ш|шоссе|г|город|обл|область|пр|проспект)

                (?= [^а-яА-ЯЁё] | $ )

                [.,-]*
            }x',
            '',
            $rawAddress
        );

        return trim($cleanAddress);
    }

    public function getAddressLevelId($code)
    {
        $result = $this->db->execute(
            'SELECT id FROM address_object_levels WHERE code = ?q',
            [$code]
        )->fetchResult();

        if ($result === null) {
            throw new BadRequestException('Некорректное значение уровня адреса: ' . $code);
        }

        return $result;
    }
}
