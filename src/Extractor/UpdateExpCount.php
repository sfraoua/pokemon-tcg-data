<?php

namespace App\Extractor;

use App\Model\Db;
use Curl\Curl;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\BulkWrite;

class UpdateExpCount
{
    public function Import(){

        $collection = Db::getClient()->selectCollection('cards', 'expansions');
        $tsv = file_get_contents(__DIR__.'/exp-count/count.tsv');

        $rows = explode(PHP_EOL, $tsv);

        foreach ($rows as $row){
            $cols = explode("\t", $row);
            if(count($cols) != 2){
                var_dump($cols);
                die("Wrong cols number");
            }


            $nbs = trim(preg_replace('/[a-zA-Zé]+/', "", $cols[0]));
            $nbs = preg_replace('!\s+!', ' ', $nbs);

            $exp = explode(" ", $nbs);
            $count = 0;
            foreach ($exp as $nb){
                $count += $nb;
            }

            $res = $collection->updateOne(["abbr"=>trim($cols[1]), "game"=>"pokemon"], ['$set'=>['cardsCount'=>$count]], []);


        }

//
//        $bulk = new BulkWrite();
//        foreach ($cursor as $expansion) {
//            $path = __DIR__.'/../../cards/en/'.$expansion->code.'.json';
//            if(!file_exists($path)){
//                echo 'Missing '.$expansion->code.'.json'.PHP_EOL;
//                continue;
//            }
//            $jsonCards = json_decode(file_get_contents($path), true);
//            foreach ($jsonCards as $card){
//
//                $bulk->insert([
//                    'name'=>['en'=>$card['name']],
//                    'expansion'=>$expansion->_id,
//                    'fullId'=>$card['id'],
//                    'game'=>'pokemon',
//                    'category'=>$card['supertype'],
//                    'subCategories'=>$card['subtypes'],
//                    'image'=>[
//                        'en'=>[
//                            'src'=>"/assets/pokemon/cards/".str_replace('https://images.pokemontcg.io', '', $card['images']['large']),
//                            'thumb'=>"/assets/pokemon/cards/".str_replace('https://images.pokemontcg.io', '', $card['images']['small'])
//                        ]
//                    ],
//                ]);
//
//            }
//        }
//
//        $result = Db::getClient()->getManager()->executeBulkWrite('cards.cards', $bulk);
//        var_dump($result);


//        $bulk = new BulkWrite();
//
//        $currentSet = "";
//        $offcount = 9000;
//        foreach ($cursor as $card) {
//            if($currentSet != $card->expansion){
//                $currentSet = (string)$card->expansion;
//                $offcount = 9000;
//            }
//            if(!is_numeric($card->number)){
//
//                echo $card->fullId.': '.$number.PHP_EOL;
//            }
//            $number = intval(preg_replace('/[^0-9]+/', '', $card->number));
//
//            if($number == 0){
//                $number = $offcount;
//                $offcount++;
//            } else {
//                if(strpos($card->number, $number."") === false){
//                 echo $card->fullId.': Error: '.$number.PHP_EOL;
//                }
//            }
//
////            $bulk->update(['_id'=>$card->_id], ['$set'=>['order'=>$number]], ['upsert' => false]);
//        }

//        $result = Db::getClient()->getManager()->executeBulkWrite('cards.pokemonCards', $bulk);
    }
}
