<?php

namespace Service;

use Doctrine\DBAL\Connection;

class PartsService
{
    public function generateGeoJson(Connection $connection, $partsParam)
    {
        $result[] = '{"type":"FeatureCollection","features":[';

        $first = true;

        foreach ($this->getParts($connection, $partsParam) as $part)
        {
            $result[] = ($first ? '' : ',') . $part['geo_json_content'];

            $first = false;
        }

        $result[] = ']}';

        return join('', $result);
    }

    private function getConditions(Connection $connection, $partsParam)
    {
        $conditions = [];

        foreach (explode(',', $partsParam) as $square)
        {
            if (strpos($square, ':') === false)
            {
                continue;
            }

            list($partLocation, $partNumbersRaw) = explode(':', $square);

            if (empty($partLocation) || empty($partNumbersRaw))
            {
                continue;
            }

            $partNumbers = explode('|', $partNumbersRaw);

            if (count($partNumbers) > 0)
            {
                $quotedPartNumbers = join(', ', array_map(function($partNumber) use ($connection) {
                    return $connection->quote($partNumber);
                }, $partNumbers));

                $conditions[] = join('', [
                    '(',
                    'part_location = ' . $connection->quote($partLocation),
                    ' AND ',
                    'part_number IN (' . $quotedPartNumbers . ')',
                    ')',
                ]);
            }
        }

        return $conditions;
    }

    private function getParts(Connection $connection, $partsParam)
    {
        $conditions = $this->getConditions($connection, $partsParam);

        if (empty($conditions))
        {
            return [];
        }

        return $connection->fetchAll('
            SELECT
                *
            FROM
                parts_2016_raw
            WHERE ' .
                join(' OR ', $conditions) .
            'LIMIT 1000'
        );
    }
}