<?php
namespace App\Model;
use MongoDB\Driver\ServerApi;
use MongoDB\Client;

class Db
{
    private static ?Client $client;

    public static function getClient(): Client{
        if (empty(self::$client)) {

            $uri = 'mongodb+srv://mockapi:GUegQKSrS39EnhHy@cluster0.ricvtjh.mongodb.net/?retryWrites=true&w=majority';

// Set the version of the Stable API on the client
            $apiVersion = new ServerApi(ServerApi::V1);

// Create a new client and connect to the server
            self::$client = new Client($uri, [], ['serverApi' => $apiVersion]);

            try {
                // Send a ping to confirm a successful connection
                self::$client->selectDatabase('admin')->command(['ping' => 1]);
                echo "Pinged your deployment. You successfully connected to MongoDB!\n";
            } catch (Exception $e) {
                printf($e->getMessage());
            }
        }
        return self::$client;
    }
}
