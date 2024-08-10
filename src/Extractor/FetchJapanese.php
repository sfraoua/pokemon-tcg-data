<?php

namespace App\Extractor;

use App\Model\Db;
use Curl\Curl;
use MongoDB\BSON\ObjectId;
use MongoDB\Driver\BulkWrite;
use PHPHtmlParser\Dom;

class FetchJapanese
{
    public function Import()
    {
        $path    = __DIR__.'/JapaneseData';
        $files = scandir($path);
        $files = array_diff(scandir($path), array('.', '..'));

        foreach ($files as $file) {
            $raw = file_get_contents($path.'/'.$file);

            $rows = explode( PHP_EOL, $raw);
            $cards = [];

            foreach ($rows as $row){
                if (empty($row)){
                    continue;
                }
                $cols = explode("\t",$row);
                if (filter_var($cols[7], FILTER_VALIDATE_URL) === FALSE) {
                    var_dump($cols,  $file);
                    die('Not a valid URL');
                }

                $dom = new Dom();
                $saveFileName =  str_replace('.php', '.html',(basename($cols[7])));
                if (!file_exists(__DIR__.'/jpn-html/'.$saveFileName)){
                    $dom->loadFromUrl($cols[7]);
                    file_put_contents(__DIR__.'/jpn-html/'.$saveFileName, $dom->find("html")->innerHtml());
                } else {
                    $dom->loadFromFile(__DIR__.'/jpn-html/'.$saveFileName);
                }

                //pokemon card header
                $header = $dom->find('p.dataField.cardNamePoke');
                if ($header->count() === 1){
                    $cards[] = $this->fetchPokemonCardData($dom);
                } else{
                    $cards[] = $this->fetchTrainerCardData($dom);
                }
            }

            file_put_contents(__DIR__.'/jpn-cards/'.str_replace('.tsv', '.json', $file), json_encode($cards));
        }

    }

    private function fetchPokemonCardData(Dom $dom)
    {
        $card  = [
            'category'=>'pokemon',
            'subCategory'=>'',
            'name'=>'',
            'img'=>'',
            'stage'=>'',
            'hp'=>'',
            'artist'=>'',
            'rarity'=>'',
            'rules'=>[],
            'abilities'=>[],
            'attacks'=>[],
            'retreat'=>[],
            'weakness'=>['types'=>[], 'amount'=>[]],
            'resistance'=>['types'=>[], 'amount'=>[]],
        ];
        $header = $dom->find('p.dataField.cardNamePoke');
        $dom2 = new Dom;
        $dom2->loadStr($header->toArray()[0]->innerHtml());
        $spans = $dom2->find('span');

        // Stage + HP
        if ($spans->count() === 2){
            $card['stage'] = trim($spans[0]->innerText());
            if($card['stage'] === "トレーナー"){
                $card['category'] = "trainer";
                $card['subCategory'] = "trainer";
            }
            else if($card['stage'] === "サポーター"){
                $card['category'] = "trainer";
                $card['subCategory'] = "supporter";
            }
            else if($card['stage'] === "ポケモンのどうぐ"){
                $card['category'] = "trainer";
                $card['subCategory'] = "item";
            }
            else if($card['stage'] === "スタジアム"){
                $card['category'] = "trainer";
                $card['subCategory'] = "stadium";
            }
            else if($card['stage'] === "ワザマシン"){
                $card['category'] = "trainer";
                $card['subCategory'] = "technical machine";
            }
            $card['hp'] = trim($spans[1]->innerText());
            $explodes = explode(":", $card['hp']);
            if(count($explodes) === 2){
                $card['hp'] = trim($explodes[1]);
            }
        }

        $card['name'] = trim(str_replace("HP:".$card['hp'], '', $header[0]->innerText()));
        $card['name'] = trim(str_replace($card['stage'], '', $card['name']));

        $this->fetchCommonCardDetails($card, $dom);


        //weakness
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            //weakness
            if (mb_strpos($data->innerText(), '弱点') === false ){
                return;
            }

            $card['weakness']['amount']= trim(str_replace("弱点", "", $data->innerText()));
            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                    $card['weakness']['types'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });

        //resistance
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            if (mb_strpos($data->innerText(), '抵抗力') === false ){
                return;
            }

            $card['resistance']['amount']= trim(str_replace("抵抗力", "", $data->innerText()));
            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                    $card['resistance']['types'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });

        //retreat
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            if (mb_strpos($data->innerText(), 'にげる') === false ){
                return;
            }

            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                    $card['retreat'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });




        //rule .ruleDetail
        //attack .wazaTitle .wazaDetail

        return $card;
    }

    private function fetchTrainerCardData(Dom $dom)
    {
        $card  = [
            'category'=>'trainer',
            'subCategory'=>'',
            'name'=>'',
            'img'=>'',
            'stage'=>'',
            'hp'=>'',
            'artist'=>'',
            'rarity'=>'',
            'rules'=>[],
            'abilities'=>[],
            'attacks'=>[],
            'retreat'=>[],
            'weakness'=>['types'=>[], 'amount'=>[]],
            'resistance'=>['types'=>[], 'amount'=>[]],
        ];
        $header = $dom->find('p.dataField.cardName');
        $dom2 = new Dom;
        $dom2->loadStr($header->toArray()[0]->innerHtml());
        $spans = $dom2->find('span');

        // subCategory

        $card['name'] = trim($header[0]->innerText());

        if ($spans->count() > 0){
            $sub = trim($spans[0]->innerText());
            if($sub === "トレーナー"){
                $card['subCategory'] = "trainer";
            }
            else if($sub === "サポーター"){
                $card['subCategory'] = "supporter";
            }
            else if($sub === "ポケモンのどうぐ"){
                $card['subCategory'] = "item";
            }
            else if($sub === "スタジアム"){
                $card['subCategory'] = "stadium";
            }
            else if($sub === "ワザマシン"){
                $card['subCategory'] = "technical machine";
            }
            else if($sub === "基本エネルギー" || $sub === "特殊エネルギー"){
                $card['category'] = "energy";
                $card['subCategory'] = $sub;
            }
            $card['name'] = trim(str_replace($sub, '', $card['name']));
        }

        $this->fetchCommonCardDetails($card, $dom);


        //trainerDetail
        $dom->find('p.trainerDetail')->each(function ($data) use (&$card){
            $card['rules'][] = trim($data->innerText());
        });

        //weakness
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            //weakness
            if (mb_strpos($data->innerText(), '弱点') === false ){
                return;
            }

            $card['weakness']['amount']= trim(str_replace("弱点", "", $data->innerText()));
            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                $card['weakness']['types'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });

        //resistance
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            if (mb_strpos($data->innerText(), '抵抗力') === false ){
                return;
            }

            $card['resistance']['amount']= trim(str_replace("抵抗力", "", $data->innerText()));
            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                $card['resistance']['types'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });

        //retreat
        $dom->find('span.dataField.cardInfo')->each(function ($data, $i) use (&$card){
            if (mb_strpos($data->innerText(), 'にげる') === false ){
                return;
            }

            $costDom = new Dom();
            $costDom->loadStr($data->innerHtml());
            $costDom->find("img")->each(function ($img) use (&$card){
                $card['retreat'][] = $this->imageToEnergy($img->getAttribute('src'));
            });
        });


        return $card;
    }

    private function imageToEnergy($img)
    {
        switch ($img){
            case '../../img/common/en000.png':
                return 'colorless';
            case '../../img/common/en001.png':
                return 'grass';
            case '../../img/common/en002.png':
                return 'fire';
            case '../../img/common/en003.png':
                return 'water';
            case '../../img/common/en004.png':
                return 'lightning';
            case '../../img/common/en005.png':
                return 'psychic';
            case '../../img/common/en006.png':
                return 'fighting';
            case '../../img/common/en007.png':
                return 'darkness';
            case '../../img/common/en008.png':
                return 'steel';
        }

        return "???";
    }

    private function fetchCommonCardDetails(array &$card, Dom $dom)
    {
        $src = $dom->find('img.cardImg')[0]->getAttribute('src');
        $filename = str_replace('../../img/', "", $src);
        $folder = str_replace(basename($filename), "", $filename);
        $src = "https://pcg-search.com/".(str_replace('../../', '', $src));

        // Rules
        $dom->find('p.ruleDetail')->each(function ($rule) use (&$card){
            $card['rules'][] = trim($rule->innerText());
        });
        $card['img'] = $filename;

        //img
        if (!file_exists(__DIR__.'/jpn-img/'.$filename)){
            if (!is_dir(__DIR__.'/jpn-img/' . $folder)) {
                // dir doesn't exist, make it
                mkdir(__DIR__.'/jpn-img/' . $folder);
            }
            file_put_contents(__DIR__.'/jpn-img/'.$filename, file_get_contents($src));
        }

        //abilities
        $dom->find('p.pBodyTitle')->each(function ($data, $i) use (&$card){
            $card['abilities'][$i] = ["name"=>trim($data->innerText()), "text"=>""];
        });
        $dom->find('p.pBodyDetail')->each(function ($data, $i) use (&$card){
            $card['abilities'][$i]["text"] = trim($data->innerText());
        });

        $dom->find('p.pPowerTitle')->each(function ($data, $i) use (&$card){
            $card['abilities'][$i] = ["name"=>trim($data->innerText()), "text"=>""];
        });
        $dom->find('p.pPowerDetail')->each(function ($data, $i) use (&$card){
            $card['abilities'][$i]["text"] = trim($data->innerText());
        });

        //rarity
        $dom->find('span.dataField.cardInfo')->each(function ($data) use (&$card){
            if (mb_strpos($data->innerText(), 'レアリティ') === false ){
                return;
            }
            $card['rarity'] = trim(str_replace("レアリティ", "", $data->innerText()));
        });

        //illustrator
        $dom->find('p.packName')->each(function ($data) use (&$card){
            if (mb_strpos($data->innerText(), 'illus.') === false ){
                return;
            }
            $card['artist'] = trim(str_replace("illus.", "", $data->innerText()));
        });

        //attacks
        $dom->find('div.infoBox')->each(function ($data) use (&$card){
            $dom2 = new Dom;
            $dom2->loadStr($data->innerHtml());

            $titleCol = $dom2->find("p.wazaTitle");
            if ($titleCol->count() === 0){
                return;
            }

            $attack = [
                'name'=> trim($titleCol[0]->innerText()),
                'cost'=>[],
                'damage'=>'',
                'text'=>'',
            ];

            $costDom = new Dom();
            $costDom->loadStr($titleCol[0]->innerHtml());
            $dom2->find("img")->each(function ($img) use (&$attack){
                $attack['cost'][] = $this->imageToEnergy($img->getAttribute('src'));
            });

            $exp = explode(" ", $attack['name']);
            if (count($exp)>1){
                $dmg = $exp[count($exp)-1];
                if ($dmg == '?' || intval($dmg)>0){
                    $attack['damage'] = $dmg;
                    $attack['name'] = trim(str_replace($dmg, "", $attack['name']));
                }
            }

            $textCol = $dom2->find("p.wazaDetail");
            if ($textCol->count() > 0){
                $attack['text'] = trim($textCol[0]->innerText());
            }

            $card['attacks'][] = $attack;
        });
    }
}
