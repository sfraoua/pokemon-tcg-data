<?php

namespace App\Extractor;

use App\Model\Db;
use Curl\Curl;
use MongoDB\Driver\BulkWrite;

class TcgdexFr
{
    public function Extract($locale = "fr"){
        $energies =[

                'fr' => [
                    "Incolore"=>"Colorless",
                    "Obscurité"=>"Darkness",
                    "Dragon"=>"Dragon",
                    "Fée"=>"Fairy",
                    "Combat"=>"Fighting",
                    "Feu"=>"Fire",
                    "Plante"=>"Grass",
                    "Électrique"=>"Lightning",
                    "Métal"=>"Metal",
                    "Psy"=>"Psychic",
                    "Eau"=>"Water"
                ],
                'de' => [
                    "Farblos"=>"Colorless",
                    "Unlicht"=>"Darkness",
                    "Drache"=>"Dragon",
                    "Fee"=>"Fairy",
                    "Kampf"=>"Fighting",
                    "Feuer"=>"Fire",
                    "Pflanze"=>"Grass",
                    "Elektro"=>"Lightning",
                    "Metall"=>"Metal",
                    "Psycho"=>"Psychic",
                    "Wasser"=>"Water"
                ]

        ];
        $expCollection = Db::getClient()->cards->pokemonExpansions;
        $cardsCollection = Db::getClient()->cards->pokemonCards;

        $cursor = $expCollection->find([]);
        file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img.log',"");
        file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img_error.log',"");

        foreach ($cursor as $expansion){
            if (empty($expansion->extIds->tcgdex)){
                continue;
            }

            $cards = $cardsCollection->find(['expansion'=>$expansion->_id]);
            $url = 'https://api.tcgdex.net/v2/'.$locale.'/sets/'.$expansion->extIds->tcgdex;
            $apiCards = [];

            try {
                $datas = $this->GetDataFromUrl($url);
                foreach ($datas->cards as $data){
                    $apiCards[$data->localId] = $data;
                }
            } catch (\Exception $e){
                file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img_error.log',$e->getMessage().PHP_EOL,FILE_APPEND);
                continue;
            }

            $bulk = new BulkWrite();
            foreach($cards as $card) {
                if(!empty($card->name->$locale)){
                    continue;
                }
                $apiCard = null;
                if(!empty($apiCards[$card->number])){
                    $apiCard = $apiCards[$card->number];
                }
                elseif(!empty($apiCards['00'.$card->number])){
                    $apiCard = $apiCards['00'.$card->number];
                }
                elseif(!empty($apiCards['0'.$card->number])){
                    $apiCard = $apiCards['0'.$card->number];
                }

                if(empty($apiCard)){
                    file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"MISS: ".$expansion->setId." | ".$card->number." | ".$card->name->en.PHP_EOL,FILE_APPEND);
                    continue;
                }

                $cardUrl = 'https://api.tcgdex.net/v2/'.$locale.'/sets/'.$expansion->extIds->tcgdex.'/'.$apiCard->localId;
                try {
                    $apiCardDetails = $this->GetDataFromUrl($cardUrl);
                } catch (\Exception $e){
                    file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"DWL_ERR: ".$expansion->setId." | ".$card->number." | ".$card->name->en.PHP_EOL,FILE_APPEND);
                    file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_error.log',"Failed fetching card: ".$expansion->setId." | ".$card->number." | ".$cardUrl.PHP_EOL,FILE_APPEND);
                    continue;
                }

                $updates = [];
                if(!empty($apiCardDetails->name) ){
                    $updates['name.'.$locale] = $apiCardDetails->name;
                }
                if(!empty($apiCardDetails->description)){
                    $updates['flavorText.'.$locale] = $apiCardDetails->description;
                }
                if(!empty($apiCardDetails->abilities)){
                    $abilities = [];
                    foreach ($apiCardDetails->abilities as $ability) {
                        $abilities[] = [
                            'type'=> $ability->type,
                            'name'=> $ability->name,
                            'text'=> $ability->effect
                        ];
                    }

                    $updates['abilities.'.$locale] = $abilities;
                }

                if(!empty($apiCardDetails->attacks)){
                    $attacks = [];
                    foreach ($apiCardDetails->attacks as $attack) {
                        $cost = [];
                        foreach ($attack->cost as $c){
                            $cost[] = $energies[$locale][$c];
                        }
                        $attacks[] = [
                            'cost'=> $cost,
                            'damage'=> !empty($attack->damage)?$attack->damage."":"",
                            'name'=> $attack->name,
                            'text'=> $attack->effect ?? ''
                        ];
                    }

                    $updates['attacks.'.$locale] = $attacks;
                }


                if (empty($updates)){
                    file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"SKIP: ".$expansion->setId." | ".$card->number." | ".$card->name->en.PHP_EOL,FILE_APPEND);
                    continue;
                }

                $bulk->update(['_id'=>$card->_id], ['$set'=>$updates], ['upsert' => false]);

                file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"UPDATE: ".$expansion->setId." | ".$card->number." | ".$card->name->en.PHP_EOL,FILE_APPEND);
            }
            if($bulk->count() == 0){
                file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"---------- UPDATES No operations to handle  ".$expansion->setId.PHP_EOL,FILE_APPEND);
                continue;
            }
            $result = Db::getClient()->getManager()->executeBulkWrite('cards.pokemonCards', $bulk);
            file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'.log',"---------- UPDATES  ".$expansion->setId." | ".$result->getModifiedCount()."/".$result->getMatchedCount().PHP_EOL,FILE_APPEND);
        }

    }
    public function ExtractImages($locale = 'fr'){

        $expCollection = Db::getClient()->cards->pokemonExpansions;
        $cardsCollection = Db::getClient()->cards->pokemonCards;

        $cursor = $expCollection->find([]);
        file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img.log',"");
        file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img_error.log',"");

        foreach ($cursor as $expansion){
            if (empty($expansion->extIds->tcgdex)){
                continue;
            }

            $cards = $cardsCollection->find(['expansion'=>$expansion->_id]);
            $url = 'https://api.tcgdex.net/v2/'.$locale.'/sets/'.$expansion->extIds->tcgdex;
            $apiCards = [];
            try {
                $datas = $this->GetDataFromUrl($url);
                foreach ($datas->cards as $data){
                    $apiCards[$data->localId] = $data;
                }
            } catch (\Exception $e){
                file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img_error.log',$e->getMessage().PHP_EOL,FILE_APPEND);
                continue;
            }

            $bulk = new BulkWrite();
            foreach($cards as $card) {
                $apiCard = null;
                if(!empty($apiCards[$card->number])){
                    $apiCard = $apiCards[$card->number];
                }
                elseif(!empty($apiCards['00'.$card->number])){
                    $apiCard = $apiCards['00'.$card->number];
                }
                elseif(!empty($apiCards['0'.$card->number])){
                    $apiCard = $apiCards['0'.$card->number];
                }

                if(empty($apiCard)){
                    file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img.log',"MISS: ".$expansion->setId." | ".$card->number." | ".$card->name->en.PHP_EOL,FILE_APPEND);
                    continue;
                }

                if(empty($apiCard->image) || !empty($card->image->$locale) || empty($card->image->en))
                {
                    continue;
                }

                $image = $card->image->en->getArrayCopy();

                if(empty($image['src'])){
                    continue;
                }

                $folder = explode('/', $image['src'])[0];
                $imgFr = [
                    'src' => str_replace('-en-', '-'.$locale.'-', $image['src']),
                    'thumb' => str_replace('-en-', '-'.$locale.'-', $image['thumb']),
                ];

                if(!is_dir(__DIR__.'/../../downloads/'.$folder)) {
                    mkdir(__DIR__.'/../../downloads/'.$folder, 777);
                    mkdir(__DIR__.'/../../downloads/'.$folder.'/thumbs', 777);
                }

                if(!file_put_contents(__DIR__.'/../../downloads/'.$imgFr['src'], file_get_contents($apiCard->image.'/high.webp'))){
                   continue;
                }
                file_put_contents(__DIR__.'/../../downloads/'.$imgFr['thumb'], file_get_contents($apiCard->image.'/low.webp'));


                $bulk->update(['_id'=>$card->_id], ['$set'=>['image.'.$locale=>$imgFr]], ['upsert' => false]);
            }

            if($bulk->count() == 0){
                continue;
            }
            $result = Db::getClient()->getManager()->executeBulkWrite('cards.pokemonCards', $bulk);
            file_put_contents(__DIR__.'/../../logs/cards_'.$locale.'_img.log',"---------- UPDATES  ".$expansion->setId." | ".$result->getModifiedCount()."/".$result->getMatchedCount().PHP_EOL,FILE_APPEND);
        }
    }

    public function GetDataFromUrl($url) {
        $curl = new Curl();
        $curl->get($url);

        if ($curl->error) {
            throw new \Exception($curl->errorMessage);
//            echo 'Error: ' . $curl->errorMessage . "\n";
//            $curl->diagnose();
        }

        return $curl->response;
    }
}
