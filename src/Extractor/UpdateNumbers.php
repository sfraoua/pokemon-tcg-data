<?php

namespace App\Extractor;

use App\Model\Db;
use Curl\Curl;
use MongoDB\Driver\BulkWrite;

class UpdateNumbers
{
    public function UpdateCards(){
        $collection = Db::getClient()->selectCollection('cards', 'pokemonCards');
        $cursor = $collection->find([]);


        $bulk = new BulkWrite();

        $currentSet = "";
        $offcount = 9000;
        foreach ($cursor as $card) {
            if($currentSet != $card->expansion){
                $currentSet = (string)$card->expansion;
                $offcount = 9000;
            }
            if(!is_numeric($card->number)){

                echo $card->fullId.': '.$number.PHP_EOL;
            }
            $number = intval(preg_replace('/[^0-9]+/', '', $card->number));

            if($number == 0){
                $number = $offcount;
                $offcount++;
            } else {
                if(strpos($card->number, $number."") === false){
                 echo $card->fullId.': Error: '.$number.PHP_EOL;
                }
            }

//            $bulk->update(['_id'=>$card->_id], ['$set'=>['order'=>$number]], ['upsert' => false]);
        }

//        $result = Db::getClient()->getManager()->executeBulkWrite('cards.pokemonCards', $bulk);
    }
}
