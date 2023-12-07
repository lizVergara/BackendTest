<?php

namespace App\Models;

use Illuminate\Console\View\Components\Warn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class Order extends Model
{
    use HasFactory;
    protected $fillable = ["price", "detail", "destination_lat", "destination_lng", "driver_id", "store_id", "street", "time"];

    //relations
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    protected $with = ["store"];

    public function asignDriver()
    {
        $allDrivers = Driver::all();
        $availableDrivers = $allDrivers->filter(function ($driver) {
            return $driver->orders->count() < 2;
        });

        $closestDriver = null;
        $closestDistance = PHP_INT_MAX;
        // $available_drivers = array();
        // $drivers = Driver::withCount("orders")->get();
        // foreach ($drivers as $driver) {
        //     if ($driver->orders_count < 2) {
        //         $available_drivers[] = $driver;
        //     }
        // }
        $store_lat = $this->store->lat;
        $store_lng =  $this->store->lng;
        $array_resp = array();
        try {
            foreach ($availableDrivers  as $driver) {
                $distance = $this->GetDrivingDistance($store_lat, $driver->lat, $store_lng, $driver->lng);
                if ($distance['distance']['value'] < $closestDistance) {
                    $closestDriver = $driver;
                    $closestDistance = $distance['distance']['value'];
                }
                // $resp = [
                //     "driver" => $driver->name,
                //     "driver_id" => $driver->id,
                //     "distance" => $this->GetDrivingDistance($store_lat, $driver->lat, $store_lng, $driver->lng)
                // ];
                // $array_resp[] = $resp;
            }
        } catch (\Throwable $th) {
            error_log($th);
        }
        if ($closestDriver) {
            // Asignar la orden al conductor más cercano
            DB::beginTransaction();
            try {
                $this->driver_id = $closestDriver->id;
                $this->save();
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                error_log($th);
            }

            return [
                'driver' => $closestDriver->name,
                'driver_id' => $closestDriver->id,
                'distance' => $closestDistance,
            ];
        }


        // usort($array_resp, fn ($a, $b) => $a['distance']['distance']['value'] <=> $b['distance']['distance']['value']);

        // return $array_resp[0];
    }
    function GetDrivingDistance($lat1, $lat2, $long1, $long2)
    {
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . $lat1 . "," . $long1 . "&destinations=" . $lat2 . "," . $long2 . "&mode=driving&language=en-USL&key=" . env("GOOGLE_API");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        $response_a = json_decode($response, true);
        if ($response_a['rows'][0]['elements'][0]['distance'] ?? null) {
            $dist = $response_a['rows'][0]['elements'][0]['distance']['text'];
            $dis_value = $response_a['rows'][0]['elements'][0]['distance']['value'];

            $time = $response_a['rows'][0]['elements'][0]['duration']['text'];
            $time_value = $response_a['rows'][0]['elements'][0]['duration']['value'];
        } else {
            $dist = "undefined";
            $dis_value = 9999999999;
            $time = "undefined";
            $time_value = 9999999999;
        }

        return array('distance' => ["text" => $dist, "value" => $dis_value], 'time' => ["text" => $time, "value" => $time_value]);
    }

}