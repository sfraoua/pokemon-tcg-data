<?php

namespace App\Extractor;

use App\Model\Db;
use Curl\Curl;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\BulkWrite;

class CheckCardsCountByExpCount
{
    public function Import(){

        $collection = Db::getClient()->selectCollection('cards', 'expansions');
        $collectionCards = Db::getClient()->selectCollection('cards', 'cards');
        $expansions = $collection->find([]);

        foreach ($expansions as $expansion){


            $cards = $collectionCards->find(['expansion'=>$expansion->_id]);
            $count = count($cards->toArray());

            if ($count != $expansion->cardsCount) {

                echo $expansion->code . ' ' .$expansion->abbr . ' ' .$expansion->cardsCount . ' ' .  $count . PHP_EOL;
            }



        }

    }
}
