<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB; // Import the DB facade

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialDetail;
use App\Models\Inventory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;


use TCPDF;


use Illuminate\Support\Facades\http;

class InventoryAPIController extends Controller
{
    protected $httpClient;

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }  
    
    
    
    public function storeMaterial(Request $request)
    {
        set_time_limit(120); // Sets the time limit to 2 minutes (120 seconds)
        $this->validate($request, [
            'img' => 'required|image',
            'name' => 'required|string',
            'category' => 'required|string',
        ]);

        // Store the material image (you may need to adjust the storage configuration)
        $imagePath = $request->file('img')->store('materials', 'public');

        // Create a new material
        $material = new Material();
        $material->img = $imagePath;
        $material->name = $request->input('name');
        $material->category = $request->input('category');
        $material->save();

        return response()->json($material, 201);
    }


    // Create a new material detail
    public function storeMaterialDetail(Request $request)
    {
        // Validate the incoming request data
        $this->validate($request, [
            'material_id' => 'required|exists:materials,id',
            'size' => 'nullable|string',
            'type' => 'nullable|string',
            'color' => 'nullable|string',
            'other_details' => 'nullable|string',
        ]);

        // Create a new material detail
        $materialDetail = new MaterialDetail();
        $materialDetail->material_id = $request->input('material_id');
        $materialDetail->size = $request->input('size');
        $materialDetail->type = $request->input('type');
        $materialDetail->color = $request->input('color');
        $materialDetail->other_details = $request->input('other_details');
        $materialDetail->save();

        return response()->json($materialDetail, 201);
    }

    public function storeInventory(Request $request)
    {
        // Get the last temp_id and inventory_id from their respective tables
        $lastTempId = DB::table('tempmaterials')->max('temp_id');
        $lastInventoryId = DB::table('inventory')->max('inventory_id');
    
        // Calculate the next inventory_id by incrementing the max of last temp_id and last inventory_id
        $inventoryId = max($lastTempId, $lastInventoryId) + 1;
    
        // Validate the incoming request data
        $this->validate($request, [
            'seller_id' => 'required|exists:sellers,seller_id',
            'material_id' => 'required|exists:materialdetails,detail_id',
            'availability' => 'string',
            'purchase_price' => 'required|numeric',
        ]);
    
        // Check if a record with the same seller_id and material_id exists
        $existingRecord = DB::table('inventory')
            ->where('seller_id', $request->input('seller_id'))
            ->where('material_id', $request->input('material_id'))
            ->first();
    
        if ($existingRecord) {
            // Record with the same seller_id and material_id already exists
            return response()->json(['error' => 'Inventory record already exists'], 400);
        }
    
        // Create the new inventory record
        $inventory = new \stdClass();
        $inventory->inventory_id = $inventoryId;
        $inventory->seller_id = $request->input('seller_id');
        $inventory->material_id = $request->input('material_id');
        $inventory->availability = $request->input('availability');
        $inventory->purchase_price = $request->input('purchase_price');
        $inventory->addedToCartWeekly = 0;
        $inventory->weeklySearch = 0;
    
        // Save the inventory item to the database using DB::table
        DB::table('inventory')->insert((array) $inventory);
    
        // Return a JSON response with the created inventory item
        return response()->json($inventory, 201);
    }
    
    

    
    public function getTypes(Request $request)
    {
        // Retrieve distinct types for the selected material
        $materialId = $request->input('material_id');
        $types = DB::table('materialdetails')
            ->where('material_id', $materialId)
            ->distinct()
            ->pluck('type');
        return response()->json($types, 200);
    }
    
    public function getSizes(Request $request)
    {
        // Retrieve distinct sizes for the selected material
        $materialId = $request->input('material_id');
        $sizes = DB::table('materialdetails')
            ->where('material_id', $materialId)
            ->distinct()
            ->pluck('size');
        return response()->json($sizes, 200);
    }
    
    public function getMaterials()
    {
        // Retrieve distinct material names ordered by material ID
        $materials = DB::table('materials')
            ->distinct()
            ->orderBy('material_id') // Order by material ID
            ->pluck('name');
    
        return response()->json($materials, 200);
    }
    
    

public function getSellerIdByUserId(Request $request)
{
    $this->validate($request, [
        'user_id' => 'required|integer|exists:users,id', // Ensure the user exists
    ]);

    // Retrieve the seller ID based on the user ID
    $userId = $request->input('user_id');
    $sellerId = DB::table('sellers')
    ->where('user_id', $userId)
    ->value('seller_id');

    return response()->json(['seller_id' => $sellerId], 200);
}


public function getDetailId(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'material_id' => 'required|exists:materials,material_id',
        'type' => 'nullable|string',
        'color' => 'nullable|string',
        'size' => 'nullable|string',
    ]);

    // Retrieve the request parameters
    $materialId = $request->input('material_id');
    $type = $request->input('type');
    $color = $request->input('color');
    $size = $request->input('size');

    // Build the SQL query
    $sql = "SELECT detail_id FROM materialdetails 
            WHERE material_id = :material_id 
            AND (type = :type OR type IS NULL) 
            AND (color = :color OR color IS NULL) 
            AND (size = :size OR size IS NULL)";

    // Execute the query with bindings
    $materialDetail = DB::select($sql, [
        'material_id' => $materialId,
        'type' => $type,
        'color' => $color,
        'size' => $size,
    ]);

    // Check if a result was found
    if (!empty($materialDetail)) {
        // Extract the detail_id from the result
        $detailId = $materialDetail[0]->detail_id;
        return response()->json(['detail_id' => $detailId], 200);
    } else {
        // No matching detail_id found
        return response()->json(['detail_id' => null], 200);
    }
}



public function getMaterialsId(Request $request)
{
    $this->validate($request, [
        'name' => 'string',
    ]);

    // Retrieve the request parameters
    $name = $request->input('name');
    

    $id = DB::table('materials')
    ->where('name', $name)
    
    ->value('material_id');
  

    return response()->json(['material_id' => $id], 200);
}

public function getSellerInventory(Request $request)
{
    // Retrieve the seller_id parameter from the URL
    $sellerId = $request->input('seller_id');

    // Validate the sellerId if needed
    // ...
    $inventoryItems = DB::table('inventory')
    ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
    ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
    ->select(
        'inventory.*',
        'materials.name as material_name',
        'materials.soldPer',
        DB::raw("COALESCE(materialdetails.size, 'N/A') as size"),
        DB::raw("COALESCE(materialdetails.type, 'N/A') as type"),
        DB::raw("COALESCE(materialdetails.color, 'N/A') as color"),
        DB::raw("COALESCE(materialdetails.other_details, 'N/A') as material_details"),
        DB::raw('TO_BASE64(materials.img) as material_image') // Base64 encode the blob
    )
    ->where('inventory.seller_id', $sellerId)
    ->get();

return response()->json($inventoryItems, 200);


}

// In your InventoryAPIController.php

// Search for materials based on user input
// public function searchMaterials(Request $request)
// {
//     $query = $request->input('query');
//     $sortCriteria = $request->input('sort_criteria', 'purchase_price'); // Default to sorting by distance
//     $sortOrder = $request->input('sort_order', 'asc'); // Default to ascending order

//     $materials = DB::table('materials')
//         ->join('materialdetails', 'materials.material_id', '=', 'materialdetails.material_id')
//         ->join('inventory', 'materialdetails.detail_id', '=', 'inventory.material_id')
//         ->join('sellers', 'sellers.seller_id', '=', 'inventory.seller_id')
//         ->join('users', 'users.id', '=', 'sellers.user_id')
//         ->where(function ($queryBuilder) use ($query) {
//             $queryBuilder->where('materials.name', 'LIKE', "%$query%")
//                 ->orWhere('materialdetails.type', 'LIKE', "%$query%");
//         })
//         ->select(DB::raw('TO_BASE64(materials.img) as material_image'),'users.address','materials.name as material_name', 'materialdetails.type', 'materialdetails.size', 'inventory.*', 'users.name as seller_name');

//     // Add conditional sorting based on criteria
//     if ($sortCriteria === 'purchase_price') {
//         $materials->orderBy('inventory.purchase_price', $sortOrder);
//     } elseif ($sortCriteria === 'type') {
//         $materials->orderBy('materialdetails.type', $sortOrder);
//     } elseif ($sortCriteria === 'distance') {
//         $materials->orderBy('sellers.seller_id', $sortOrder);

//     }
       

//     // Get the sorted results
//     $sortedMaterials = $materials->get();

//     return response()->json($sortedMaterials, 200);
// }

public function searchMaterials(Request $request)
{
    $query = $request->input('query');
    $sortCriteria = $request->input('sort_criteria', 'purchase_price'); // Default to sorting by purchase_price
    $sortOrder = $request->input('sort_order', 'asc'); // Default to ascending order

    $userLatitude = $request->input('user_latitude'); // Get latitude from the Android app
    $userLongitude = $request->input('user_longitude'); // Get longitude from the Android app


    // Check if the query exists in tempmaterials
    $tempMaterialsResult = DB::table('tempmaterials')
        ->join('sellers', 'sellers.seller_id', '=', 'tempmaterials.seller_id')
        ->join('users', 'users.id', '=', 'sellers.user_id')
        ->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('tempmaterials.name', 'LIKE', "%$query%")
                ->orWhere('tempmaterials.type', 'LIKE', "%$query%");
        })
        ->where('tempmaterials.availability', 'available') // Change the availability check
        ->select(
            DB::raw('TO_BASE64(tempmaterials.img) as material_image'),
            'tempmaterials.temp_id as inventory_id',
            'tempmaterials.name as material_name',
            'tempmaterials.type',
            'tempmaterials.size',
            'tempmaterials.color',
            'tempmaterials.other_details',
            'tempmaterials.weeklySearch',
            'tempmaterials.addedToCartWeekly',
            'tempmaterials.seller_id',
            'tempmaterials.purchase_price',
            'tempmaterials.totalMonthlySearch',
            'tempmaterials.totalMonthlyToCart',
            'tempmaterials.soldPer',
            'users.name as seller_name',
            'users.address as seller_address'
        )
        ->get();

    // If query exists in tempmaterials, use tempMaterialsResult; else, use materials table
    if ($tempMaterialsResult->isNotEmpty()) {
        $sortedMaterials = $tempMaterialsResult;

        $processedInventoryIds = [];

        // Increment weeklySearch for materials and track processed inventory IDs
        foreach ($sortedMaterials as $tempMaterialsResult) {
            $inventoryId = $tempMaterialsResult->inventory_id;
            if (!in_array($inventoryId, $processedInventoryIds)) {
                DB::table('tempmaterials')
                    ->where('temp_id', $inventoryId)
                    ->increment('weeklySearch');
                $processedInventoryIds[] = $inventoryId;
            }
        }
    } else {
        $sortedMaterials = DB::table('materials')
            ->join('materialdetails', 'materials.material_id', '=', 'materialdetails.material_id')
            ->join('inventory', 'materialdetails.detail_id', '=', 'inventory.material_id')
            ->join('sellers', 'sellers.seller_id', '=', 'inventory.seller_id')
            ->join('users', 'users.id', '=', 'sellers.user_id')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('materials.name', 'LIKE', "%$query%")
                    ->orWhere('materialdetails.type', 'LIKE', "%$query%");
            })
            ->where('inventory.availability', 'available') // Change the availability check
            ->select(
                DB::raw('TO_BASE64(materials.img) as material_image'),
                'materials.name as material_name',
                'materialdetails.type',
                'materialdetails.size',
                'materials.soldPer',
                'inventory.*',
                'users.name as seller_name',
                'users.address as seller_address'
            );

            if ($sortCriteria === 'distance') {
                // sorted in the app itself
                
                }elseif ($sortCriteria === 'type') {
                // Sorting by type
                $sortedMaterials->orderBy('materialdetails.type', $sortOrder);
                } elseif ($sortCriteria === 'price') {
                // Sorting by price
                $sortedMaterials->orderBy('inventory.purchase_price', $sortOrder);
                }

                $sortedMaterials = $sortedMaterials->get();



            $processedInventoryIds = [];

            // Increment weeklySearch for materials and track processed inventory IDs
            foreach ($sortedMaterials as $material) {
                $inventoryId = $material->inventory_id;
                if (!in_array($inventoryId, $processedInventoryIds)) {
                    DB::table('inventory')
                        ->where('inventory_id', $inventoryId)
                        ->increment('weeklySearch');
                    $processedInventoryIds[] = $inventoryId;
                }
            }
           
            
            


    }
        // Track processed inventory IDs to ensure weeklySearch is incremented only once per unique inventory_id
    
    // Add conditional sorting based on criteria 


    // Get the seller location for each material
    $sellerLocations = [];
    foreach ($sortedMaterials as $material) {
        $sellerAddress = $material->seller_address;

        if (!isset($sellerLocations[$sellerAddress])) {
            $sellerLocations[$sellerAddress] = $this->geocodeAddress($sellerAddress);
        }
    }

    // Calculate the distance for each material
    foreach ($sortedMaterials as $material) {
        $sellerLocation = $sellerLocations[$material->seller_address];

        if ($sellerLocation) {
            $distance = $this->calculateDistance($userLatitude, $userLongitude, $sellerLocation['latitude'], $sellerLocation['longitude']) * 1000; // Convert to meters

            $distance = round($distance, 1);


            $material->distance = $distance;
        }
    }
    

    Log::info('API Response: ' . json_encode(['materials' => $sortedMaterials]));

    return response()->json($sortedMaterials, 200);
}


public function sortMaterialsByPrice(Request $request)
{
    $query = $request->input('query');

    $sortOrder = $request->input('sort_order'); // 'asc' for ascending, 'desc' for descending

    $materials = DB::table('materials')
        ->join('materialdetails', 'materials.material_id', '=', 'materialdetails.material_id')
        ->join('inventory', 'materialdetails.detail_id', '=', 'inventory.material_id')
        ->join('sellers', 'sellers.seller_id', '=', 'inventory.seller_id')
        ->join('users', 'users.id', '=', 'sellers.user_id')
        ->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('materials.name', 'LIKE', "%$query%")
                ->orWhere('materialdetails.type', 'LIKE', "%$query%");
        })
        ->select(DB::raw('TO_BASE64(materials.img) as material_image'),'materials.name as material_name', 'materialdetails.type', 'materialdetails.size', 'inventory.*', 'users.name as seller_name')
        ->orderBy('inventory.price', $sortOrder) // Order by price
        ->get();

    return response()->json($materials, 200);
}

public function sortMaterialsByType(Request $request)
{
    $query = $request->input('query');

    $sortOrder = $request->input('sort_order'); // 'asc' for ascending, 'desc' for descending

    $materials = DB::table('materials')
        ->join('materialdetails', 'materials.material_id', '=', 'materialdetails.material_id')
        ->join('inventory', 'materialdetails.detail_id', '=', 'inventory.material_id')
        ->join('sellers', 'sellers.seller_id', '=', 'inventory.seller_id')
        ->join('users', 'users.id', '=', 'sellers.user_id')
        ->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('materials.name', 'LIKE', "%$query%")
                ->orWhere('materialdetails.type', 'LIKE', "%$query%");
        })
        ->select(DB::raw('TO_BASE64(materials.img) as material_image'),'materials.name as material_name', 'materialdetails.type', 'materialdetails.size', 'inventory.*', 'users.name as seller_name')
        ->orderBy('materialdetails.type', $sortOrder) // Order by type
        ->get();

    return response()->json($materials, 200);
}




public function getSellerInfo(Request $request)
{
    $sellerId = $request->input('seller_id');

    try {
        // Retrieve the seller's information based on seller_id
        $sellerInfo = DB::table('sellers')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->where('sellers.seller_id', $sellerId) // Use $sellerId instead of $seller_id
            ->select('users.address', 'users.contact')
            ->first();

        if (!$sellerInfo) {
            return response()->json(['error' => 'Seller not found'], 404);
        }

        return response()->json($sellerInfo, 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Server error'], 500);
    }
}

public function getUserInfoByName(Request $request)
{
    $userName = $request->input('user_name'); // Assuming 'user_name' is the parameter for the user's name.

    try {
        // Retrieve the user's information based on user name
        $userInfo = DB::table('users')
            ->where('name', $userName)
            ->select('address', 'contact')
            ->first();

        if (!$userInfo) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($userInfo, 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Server error'], 500);
    }
}


public function updateInventoryItem(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'inventory_id' => 'required|exists:inventory,inventory_id',
        'detail_id' => 'required|exists:materialdetails,detail_id',
        'purchase_price' => 'nullable|numeric',
        'availability' => 'nullable|string',
    ]);

    // Retrieve the request parameters
    $inventoryId = $request->input('inventory_id');
    $detailId = $request->input('detail_id');
    $price = $request->input('purchase_price');
    $availability = $request->input('availability');

    // Use raw SQL query to update the inventory item
    $updatedRows = DB::update(
        'UPDATE inventory 
         SET material_id = ?, purchase_price = ?, availability = ? 
         WHERE inventory_id = ?',
        [$detailId, $price, $availability, $inventoryId]
    );

    if ($updatedRows > 0) {
        // Successfully updated the inventory item
        return response()->json(['message' => 'Inventory item updated successfully'], 200);
    } else {
        // No matching inventory item found for the given inventory_id
        return response()->json(['error' => 'Inventory item not found'], 404);
    }
}

public function addToCart(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'buyer_id' => 'required|exists:buyers,buyer_id',
        'inventory_id' => 'required',
    ]);

    // Capture the buyer_id, seller_id, and inventory_id from the request
    $buyerId = $request->input('buyer_id');
    $inventoryId = $request->input('inventory_id');

    try {
        // Check if the inventory_id exists in the inventory table
        $inventoryExists = DB::table('inventory')->where('inventory_id', $inventoryId)->exists();

        if (!$inventoryExists) {
            // If inventory_id doesn't exist in inventory table, check in tempmaterials table
            $tempMaterial = DB::table('tempmaterials')->where('temp_id', $inventoryId)->first();

            if ($tempMaterial) {
                // Increment addedToCartWeekly in tempmaterials table for the specific temp_id
                DB::table('tempmaterials')->where('temp_id', $inventoryId)->increment('addedToCartWeekly');
                
                // Insert a new record into the shopping_cart table using temp_id from tempmaterials table
                DB::insert('INSERT INTO shopping_cart (buyer_id, inventory_id) VALUES (?, ?)', [$buyerId, $inventoryId]);

                // Return a success response if the item was added to the cart
                return response()->json(['message' => 'Item added to the shopping cart'], 200);
            } else {
                // Return an error response if the inventory_id doesn't exist in both inventory and tempmaterials tables
                return response()->json(['error' => 'Invalid inventory ID'], 400);
            }
        }

        // Increment addedToCartWeekly in the inventory table for the specific inventory_id
        DB::table('inventory')->where('inventory_id', $inventoryId)->increment('addedToCartWeekly');

        // Insert a new record into the shopping_cart table using inventory_id
        DB::insert('INSERT INTO shopping_cart (buyer_id, inventory_id) VALUES (?, ?)', [$buyerId, $inventoryId]);

        // Return a success response if the item was added to the cart
        return response()->json(['message' => 'Item added to the shopping cart'], 200);
    } catch (\Exception $e) {
        // Return an error response if the item couldn't be added
        return response()->json(['error' => 'Failed to add item to the shopping cart'], 500);
    }
}



public function getBuyerId(Request $request)
{
    $user_id = $request->input('user_id');
    $buyer_id = DB::table('buyers')
        ->where('user_id', $user_id)
        ->value('buyer_id');

    if ($buyer_id) {
        return response()->json(['buyer_id' => $buyer_id], 200);
    } else {
        return response()->json(['error' => 'Buyer not found'], 404);
    }
}

public function getCartDetails(Request $request)
{
    $inventoryId = $request->input('inventory_id');

    try {
        // Retrieve the inventory item based on the provided inventory_id
        $inventoryItem = DB::table('inventory')
            ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
            ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
            ->join('sellers', 'inventory.seller_id', '=', 'sellers.seller_id')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->where('inventory.inventory_id', $inventoryId)
            ->select(
                DB::raw('TO_BASE64(materials.img) as material_image'),
                'materials.name as material_name',
                DB::raw('COALESCE(materialdetails.type, "N/A") as type'),
                'materialdetails.size',
                'inventory.*',
                'users.name as seller_name'
            )
            ->first(); // Use first() to get a single item

        if (!$inventoryItem) {
            return response()->json(['error' => 'Inventory item not found'], 404);
        }

        return response()->json(['inventory_item' => $inventoryItem], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to retrieve inventory item'], 500);
    }
}

public function getShoppingCartItems(Request $request)
{

    $buyerId = $request->input('buyer_id');

    try {
        // Retrieve shopping cart items for the specified buyer ID
        $cartItems = DB::table('shopping_cart')
        ->where('buyer_id', $buyerId)->get();

        // You may want to join with other tables to get more details about the items
        
        return response()->json(['cart_items' => $cartItems], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to retrieve shopping cart items'], 500);
    }



}

public function geocodeAddress($sellerAddress)
{

    $encodedSellerAddress = urlencode($sellerAddress);

    // Construct the URL for the Nominatim API
    $url = "https://nominatim.openstreetmap.org/search?q=$encodedSellerAddress&format=json&limit=1&countrycodes=PH";

    // Create a new Guzzle client
    $client = new \GuzzleHttp\Client([
        'curl' => [
            CURLOPT_SSL_VERIFYPEER => false,
        ],
    ]);

    // Send a GET request to the Nominatim API
    $response = $client->get($url);

    // Check if the request was successful
    if ($response->getStatusCode() === 200) {
        // Parse the JSON response
        $data = json_decode($response->getBody(), true);

        // Check if there is at least one result
        if (!empty($data)) {
            // Extract latitude and longitude from the first result
            $latitude = $data[0]['lat'];
            $longitude = $data[0]['lon'];

            // Log latitude and longitude separately
            \Log::info("Latitude: $latitude, Longitude: $longitude");

            // Return the latitude and longitude as an associative array
            return ['latitude' => $latitude, 'longitude' => $longitude];
        }
    }

    // If the request failed or there were no results, return null
    return null;
}


public function geocodeAddressAPI(Request $request)
{
    $sellerAddress = $request->input('address');


    $encodedSellerAddress = urlencode($sellerAddress);

    // Construct the URL for the Nominatim API
    $url = "https://nominatim.openstreetmap.org/search?q=$encodedSellerAddress&format=json&limit=1&countrycodes=PH";

    // Create a new Guzzle client
    $client = new \GuzzleHttp\Client([
        'curl' => [
            CURLOPT_SSL_VERIFYPEER => false,
        ],
    ]);

    // Send a GET request to the Nominatim API
    $response = $client->get($url);

    // Check if the request was successful
    if ($response->getStatusCode() === 200) {
        // Parse the JSON response
        $data = json_decode($response->getBody(), true);

        // Check if there is at least one result
        if (!empty($data)) {
            // Extract latitude and longitude from the first result
            $latitude = $data[0]['lat'];
            $longitude = $data[0]['lon'];

            // Log latitude and longitude separately
            \Log::info("Latitude: $latitude, Longitude: $longitude");

            // Return the latitude and longitude as an associative array
            return ['latitude' => $latitude, 'longitude' => $longitude];
        }
    }

    // If the request failed or there were no results, return null
    return null;
}


public function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $lat1 = deg2rad((double)$lat1);
    $lon1 = deg2rad((double)$lon1);
    $lat2 = deg2rad((double)$lat2);
    $lon2 = deg2rad((double)$lon2);
    

    // Radius of the Earth in kilometers
    $earthRadius = 6371;

    // Haversine formula
    $latDifference = $lat2 - $lat1;
    $lonDifference = $lon2 - $lon1;
    $a = sin($latDifference / 2) * sin($latDifference / 2) +
        cos($lat1) * cos($lat2) * sin($lonDifference / 2) * sin($lonDifference / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    // Log the calculated distance
    Log::info("Distance: $distance km");

    return $distance;
}

public function updateUserProfile(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'id' => 'required|exists:users,id',
        'name' => 'required|string',
        'email' => 'required|email',
        'address' => 'nullable|string',
        'contact' => 'nullable|string',
    ]);

    // Retrieve the request parameters
    $userId = $request->input('id');
    $name = $request->input('name');
    $email = $request->input('email');
    $address = $request->input('address');
    $contact = $request->input('contact');

    // Use raw SQL query to update the user profile
    $updatedRows = DB::update(
        'UPDATE users 
         SET name = ?, email = ?, address = ?, contact = ? 
         WHERE id = ?',
        [$name, $email, $address, $contact, $userId]
    );

    if ($updatedRows > 0) {
        // Successfully updated the user profile
        return response()->json(['message' => 'User profile updated successfully'], 200);
    } else {
        // No matching user profile found for the given id
        return response()->json(['error' => 'User profile not found'], 404);
    }
}

public function getUserProfile(Request $request)
{

    $userId = $request->input('id');

    try {
        // Retrieve user data based on user ID using manual query
        $user = DB::table('users')
            ->select('id', 'name', 'email', 'address', 'password','contact') // Include necessary columns
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Return the user's data as JSON response
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'address' => $user->address,
            'password' => $user->password,
            'contact' => $user->contact // Note: In a real application, you should not return the password
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to retrieve user profile'], 500);
    }


    
}


public function deleteCartItem(Request $request)
{
    $recordId = $request->input('record_id');

    try {
        // Delete the cart item based on record_id using raw SQL query
        $deleted = DB::delete('DELETE FROM shopping_cart WHERE record_id = ?', [$recordId]);

        if ($deleted) {
            return response()->json(['message' => 'Shopping cart item deleted successfully'], 200);
        } else {
            return response()->json(['error' => 'Shopping cart item not found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to delete shopping cart item'], 500);
    }
}



public function generateReport(Request $request)
{
    $sellerId = $request->input('seller_id');

    // Get all inventory items for the seller
    $inventoryItems = DB::table('inventory')
        ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
        ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
        ->select(
            'inventory.*',
            'materials.name as material_name',
            DB::raw('COALESCE(materialdetails.size, "N/A") as size'),
            DB::raw('COALESCE(materialdetails.type, "N/A") as type'),
            DB::raw('COALESCE(materialdetails.color, "N/A") as color'),
            DB::raw('COALESCE(materialdetails.other_details, "N/A") as material_details'),
            DB::raw('COALESCE(historical_statistics_weekly.weekly_search, inventory.weeklySearch) as weekly_search'),
            DB::raw('COALESCE(historical_statistics_weekly.added_to_cart_weekly, inventory.addedToCartWeekly) as added_to_cart_weekly'),
            DB::raw('COALESCE(historical_statistics_monthly.monthly_search, inventory.totalMonthlySearch) as monthly_search'),
            DB::raw('COALESCE(historical_statistics_monthly.added_to_cart_monthly, inventory.totalMonthToCart) as added_to_cart_monthly')
        )
        ->leftJoin('historical_statistics_weekly', function ($join) {
            $join->on('inventory.inventory_id', '=', 'historical_statistics_weekly.inventory_id')
                ->where('historical_statistics_weekly.week', '=', DB::raw('YEARWEEK(NOW())'));
        })
        ->leftJoin('historical_statistics_monthly', function ($join) {
            $join->on('inventory.inventory_id', '=', 'historical_statistics_monthly.inventory_id')
                ->where('historical_statistics_monthly.month', '=', DB::raw('DATE_FORMAT(NOW(), "%Y-%m")'));
        })
        ->where('inventory.seller_id', $sellerId)
        ->get();

    // Create a new TCPDF instance with a larger page size
    $pdf = new TCPDF('L', PDF_UNIT, 'A3', true, 'UTF-8', false);

    // Set auto page break and margin
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

    // Set document properties
    $pdf->SetCreator('Your Name');
    $pdf->SetAuthor('Your Name');
    $pdf->SetTitle('Inventory Report');
    $pdf->SetSubject('Inventory Report');

    // Add a page
    $pdf->AddPage();

    // Set font for headers
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INVENTORY REPORT', 0, 1, 'C');
    $pdf->Ln(10); // Add some space between the title and the table
    
    // Define column widths for the table
    $colWidths = array(40, 40, 40, 40, 40, 50, 50, 50, 50); // Adjust the widths as needed

    // Headers for the table
    $headers = array(
        'Material Name',
        'Size',
        'Type',
        'Color',
        'Details',
        'Weekly Searches',
        'Added to Cart Weekly',
        'Monthly Searches',
        'Added to Cart Monthly'
    );

    // Add table headers to PDF
    foreach ($headers as $key => $header) {
        $pdf->Cell($colWidths[$key], 10, $header, 1, 0, 'C');
    }
    $pdf->Ln();

    // Set font for content
    $pdf->SetFont('helvetica', '', 10);

    // Loop through inventory items and add content to the PDF
    foreach ($inventoryItems as $item) {
        // Create a table row
        $row = array(
            $item->material_name,
            $item->size,
            $item->type,
            $item->color,
            $item->material_details,
            $item->weekly_search,
            $item->added_to_cart_weekly,
            $item->monthly_search,
            $item->added_to_cart_monthly
        );

        

        // Add table row to PDF
        foreach ($row as $key => $value) {
            $pdf->Cell($colWidths[$key], 10, $value, 1, 0, 'L');
        }
        $pdf->Ln(); // Add a line break between items
    }
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'TEMPMATERIALS REPORT', 0, 1, 'C');
    $pdf->Ln(10); // Add some space between the title and the table


    foreach ($headers as $key => $header) {
        $pdf->Cell($colWidths[$key], 10, $header, 1, 0, 'C');
    }
    // Get tempmaterials data for the seller
    $tempMaterials = DB::table('tempmaterials')
        ->select(
            'tempmaterials.name as material_name',
            'tempmaterials.size',
            'tempmaterials.type',
            'tempmaterials.color',
            'tempmaterials.other_details as material_details',
            'tempmaterials.weeklySearch',
            'tempmaterials.addedToCartWeekly',
            'tempmaterials.totalMonthlySearch',
            'tempmaterials.totalMonthlyToCart'
        )
        ->where('tempmaterials.seller_id', $sellerId)
        ->get();

    // Headers for the tempmaterials table
    $headers = array(
        'Material Name',
        'Size',
        'Type',
        'Color',
        'Details' ,
        'Weekly Searches',
        'Added to Cart Weekly',
        'Monthly Searches',
        'Added to Cart Monthly'
    );

    // Add table headers to PDF for tempmaterials data
    foreach ($headers as $header) {
        $pdf->Cell(40, 10, $header, 1, 0, 'C');
    }
    $pdf->Ln();

    // Set font for content
    $pdf->SetFont('helvetica', '', 10);

    // Loop through tempmaterials data and add content to the PDF
    foreach ($tempMaterials as $tempMaterial) {
        // Create a table row for tempmaterials data
        $row = array(
            $tempMaterial->material_name,
            $tempMaterial->size,
            $tempMaterial->type,
            $tempMaterial->color,
            $tempMaterial->material_details,
            $tempMaterial->weeklySearch,
            $tempMaterial->addedToCartWeekly,
            $tempMaterial->totalMonthlySearch,
            $tempMaterial->totalMonthlyToCart
        );

        // Add table row to PDF for tempmaterials data
        foreach ($row as $key => $value) {
            $pdf->Cell($colWidths[$key], 10, $value, 1, 0, 'L');
        }
        $pdf->Ln(); // Add a line break between items
    }

    $pdf->AddPage();
    // Set font for headers
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SELLER ORDERS', 0, 1, 'C');
    $pdf->Ln(10); // Add some space between the title and the table

    // Headers for the Seller Orders table
    $sellerOrderHeaders = array(
        'Material Name',
        'Order Amount',
        'Days',
        'Status',
        'Contact',
        'Buyer Name',
        'Price'
    );

    // Add table headers to PDF for Seller Orders
    foreach ($sellerOrderHeaders as $header) {
        $pdf->Cell(30, 10, $header, 1, 0, 'C');
    }
    $pdf->Ln();

    // Get seller orders data
    $sellerOrders = DB::table('orderstable')
    ->join('inventory', 'orderstable.inventory_id', '=', 'inventory.inventory_id')
    ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
    ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
    ->join('buyers', 'orderstable.buyer_id', '=', 'buyers.buyer_id') // Join with buyers table
    ->join('users', 'buyers.user_id', '=', 'users.id') // Join with users table to get contact number
    ->select(
        'orderstable.orderAmount',
        'orderstable.days',
        'orderstable.approve',
        'materials.name as material_name',
        'users.contact as contact',
        'users.name',
        DB::raw('(CASE WHEN orderstable.agreedPrice IS NOT NULL THEN orderstable.agreedPrice ELSE (inventory.purchase_price * orderstable.orderAmount) END) as price') // Calculate price
        )
    ->where('orderstable.seller_id', $sellerId)
    ->get();

    // Set font for content
    $pdf->SetFont('helvetica', '', 10);

    // Initialize total revenue
  // Initialize total revenue
$totalRevenue = 0;

// Loop through seller orders and add content to the PDF
foreach ($sellerOrders as $sellerOrder) {
    // Create a table row for Seller Orders
    $row = array(
        $sellerOrder->orderAmount,
        $sellerOrder->days,
        $sellerOrder->material_name,
        $sellerOrder->approve == 1 ? 'Approved' : 'Rejected', // Assuming 1 is for Approved status
        $sellerOrder->contact,
        $sellerOrder->name, // Assuming 'email' is the buyer's email
        $sellerOrder->price
    );

    // Add table row to PDF for Seller Orders
    foreach ($row as $key => $value) {
        // Format prices and total revenue with PHP or Philippine Peso sign
        if ($key === 6 || $key === 7) {
            $formattedValue = 'PHP ' . number_format($value, 2); // Assuming 2 decimal places for currency
        } else {
            $formattedValue = $value;
        }

        $pdf->Cell(30, 10, $formattedValue, 1, 0, 'L');
    }
    $pdf->Ln(); // Add a line break between items

    // Add the price to total revenue if the order is approved
    if ($sellerOrder->approve == 1) {
        $totalRevenue += $sellerOrder->price;
    }
}

// Add a row for Total Expected Revenue
$pdf->Cell(150, 10, 'Total Expected Revenue', 1, 0, 'R');
$pdf->Cell(30, 10, 'PHP ' . number_format($totalRevenue, 2), 1, 1, 'L');



    // Save the generated PDF to the public folder
    $tempFilePath = public_path('pdfs/') . uniqid('inventory_report') . '.pdf';
    $pdf->Output($tempFilePath, 'F');

    // Return the relative path to the client
    return response()->json(['download_link' => '/pdfs/' . basename($tempFilePath)]);

    
}


public function addTemporaryMaterial(Request $request)
{
    set_time_limit(120); // Sets the time limit to 2 minutes (120 seconds)
    $sellerId = $request->input('seller_id');
    $name = $request->input('name');
    $category = $request->input('category');
    $size = $request->input('size');
    $type = $request->input('type');
    $color = $request->input('color');
    $otherDetails = $request->input('other_details');
    $purchase_price = $request->input('price');
    $soldPer = $request->input('soldPer');

    
    $weeklySearch = 0;
    $addedToCartWeekly = 0;
    $totalMonthlySearch = 0;
    $totalMonthlyToCart = 0;

   
    try {

        if ($request->hasFile('image')) {
            $image = $request->file('image'); // Get the uploaded image file from the request
            $imageData = file_get_contents($image->getRealPath()); // Read image data as binary
        } else {
            // Handle the case where no image is uploaded
            $imageData = null; // Set to null if no image is uploaded
        }
        // Check if the tempmaterials table is empty
        $isTableEmpty = DB::table('tempmaterials')->doesntExist();

        // Determine the detail_id and material_id based on whether the table is empty or not
        if ($isTableEmpty) {
            // If the table is empty, get the next detail_id and material_id based on the maximum values in the respective tables
            $detailId = DB::table('materialdetails')->max('detail_id') + 1;
            $materialId = DB::table('materials')->max('material_id') + 1;
            $inventoryId = DB::table('inventory')->max('inventory_id') + 1;

        } else {
            // If the table is not empty, get the latest detail_id and material_id from the tempmaterials table
            $latestRecord = DB::table('tempmaterials')->orderBy('material_id', 'desc')->first();
            $inventoryId = DB::table('inventory')->max('inventory_id') + 1;
            $inventoryExists = DB::table('tempmaterials')->where('temp_id', $inventoryId)->exists();
            if($inventoryExists){
            $inventoryId = DB::table('tempmaterials')->max('temp_id') + 1;
            $detailId = $latestRecord->detail_id + 1;
            $materialId = $latestRecord->material_id + 1;
            }else{
                $inventoryId = DB::table('inventory')->max('inventory_id') + 1;
                $detailId = $latestRecord->detail_id + 1;
                $materialId = $latestRecord->material_id + 1;
            }
        }

        // Insert data into the tempmaterials table using raw SQL query
        DB::table('tempmaterials')->insert([
            'temp_id' => $inventoryId,
            'material_id' => $materialId,
            'seller_id' => $sellerId,
            'img' => $imageData,
            'name' => $name,
            'category' => $category,
            'detail_id' => $detailId,
            'size' => $size,
            'type' => $type,
            'color' => $color,
            'other_details' => $otherDetails,
            'weeklySearch' => $weeklySearch,
            'addedToCartWeekly' => $addedToCartWeekly,
            'totalMonthlySearch' =>  $totalMonthlySearch,
            'totalMonthlyToCart' =>   $totalMonthlyToCart,
            'purchase_price' => $purchase_price,
           'availability' => "available",
           'soldPer' => $soldPer,
  
        ]);

        // Return a success response
        return response()->json(['message' => 'Material added to temporary table successfully'], 200);
    } catch (\Exception $e) {
        // Log the exception for debugging
        error_log($e->getMessage());
        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }
}

public function getTemporaryInventory(Request $request)
{
    // Retrieve the seller_id parameter from the URL
    $sellerId = $request->input('seller_id');

    try {
        // Use raw SQL query to fetch data
        $tempMaterials = DB::select(DB::raw("SELECT * FROM tempmaterials WHERE seller_id = :sellerId"), ['sellerId' => $sellerId]);

        // Iterate through results and encode binary data to base64
        foreach ($tempMaterials as $material) {
            // Encode binary data to base64
            $material->material_image = base64_encode($material->img);
            // Remove the 'img' field from the response object
            unset($material->img);
        }
    } catch (\Exception $e) {
        // Log the error
        error_log('Error processing image: ' . $e->getMessage());
        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }

    // Return the JSON response
    return response()->json($tempMaterials, 200);
}


public function updateTemporaryMaterial(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'temp_id' => 'required|exists:tempmaterials,temp_id',
        'name' => 'nullable|string|max:255',
        'image' => 'nullable|image|mimes:jpeg,png', // Assuming max size is 2MB
        'size' => 'nullable|string|max:50',
        'type' => 'nullable|string|max:50',
        'color' => 'nullable|string|max:50',
        'other_details' => 'nullable|string',
        'purchase_price' => 'nullable|numeric',
    ]);

    // Retrieve the request parameters
    $tempId = $request->input('temp_id');
    $name = $request->input('name');
    $size = $request->input('size');
    $type = $request->input('type');
    $color = $request->input('color');
    $otherDetails = $request->input('other_details');
    $purchasePrice = $request->input('purchase_price');

    // Check if image is provided
    $imageData = null; // Default to null if no new image is uploaded

    if ($request->hasFile('image')) {
        $image = $request->file('image'); // Get the uploaded image file from the request
        $imageData = file_get_contents($image->getRealPath()); // Read image data as binary
    }

    // Retrieve the original image data from the database if no new image is uploaded
    if ($imageData === null) {
        $originalImage = DB::selectOne('SELECT img FROM tempmaterials WHERE temp_id = ?', [$tempId]);

        if ($originalImage) {
            $imageData = $originalImage->img;
        }
    }

    // Use raw SQL query to update the temporary material item
    $updatedRows = DB::update(
        'UPDATE tempmaterials 
         SET name = ?, img = ?, size = ?, type = ?, color = ?, other_details = ?, purchase_price = ? 
         WHERE temp_id = ?',
        [$name, $imageData, $size, $type, $color, $otherDetails, $purchasePrice, $tempId]
    );

    if ($updatedRows > 0) {
        return response()->json(['message' => 'Temporary item updated successfully'], 200);
    } else {
        // No matching temporary material item found for the given temp_id
        return response()->json(['error' => 'Temporary material item not found'], 404);
    }
}


public function deleteTemporaryMaterial(Request $request)
{
    $tempId = $request->input('temp_id');

    try {
        // Delete the temporary material item based on temp_id using raw SQL query
        $deleted = DB::delete('DELETE FROM tempmaterials WHERE temp_id = ?', [$tempId]);

        if ($deleted) {
            return response()->json(['message' => 'Temporary material item deleted successfully'], 200);
        } else {
            return response()->json(['error' => 'Temporary material item not found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to delete temporary material item'], 500);
    }
}

public function deleteInventoryItem(Request $request)
{
    $inventoryId = $request->input('inventory_id');

    try {
        // Delete the inventory item based on inventory_id using raw SQL query
        $deleted = DB::delete('DELETE FROM inventory WHERE inventory_id = ?', [$inventoryId]);

        if ($deleted) {
            return response()->json(['message' => 'Inventory item deleted successfully'], 200);
        } else {
            return response()->json(['error' => 'Inventory item not found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to delete inventory item'], 500);
    }
}



public function getMaterialDetails(Request $request)
{
    $this->validate($request, [
        'material_name' => 'required|string',
    ]);

    // Retrieve the material name from the request
    $materialName = $request->input('material_name');

    // Retrieve material ID based on material name
    $materialId = DB::table('materials')
        ->where('name', $materialName)
        ->value('material_id');

    // Retrieve types and sizes based on material ID
    $types = DB::table('materialdetails')
        ->where('material_id', $materialId)
        ->distinct()
        ->pluck('type');

    $sizes = DB::table('materialdetails')
        ->where('material_id', $materialId)
        ->distinct()
        ->pluck('size');

    $color = DB::table('materialdetails')
        ->where('material_id', $materialId)
        ->distinct()
        ->pluck('color');

    // Return the response with material ID, types, and sizes
    return response()->json([
        'material_id' => $materialId,
        'types' => $types,
        'sizes' => $sizes,
        'color' => $color,
    ], 200);
}


public function getAllTempMaterials()
{
    $tempMaterials = DB::table('tempmaterials')
        ->select('temp_id',  DB::raw('TO_BASE64(tempmaterials.img) as img'), 'tempmaterials.name', 'tempmaterials.color', 'tempmaterials.type', 'tempmaterials.category', 'tempmaterials.size','tempmaterials.other_details', 'sellers.seller_id', 'users.name as seller_name')
        ->join('sellers', 'tempmaterials.seller_id', '=', 'sellers.seller_id')
        ->join('users', 'sellers.user_id', '=', 'users.id')
        ->get();

    return response()->json($tempMaterials, 200);
}


public function approveTempMaterial(Request $request)
{

    $tempId = $request->input('temp_id');

   
    $tempMaterial = DB::table('tempmaterials')
        ->where('temp_id', $tempId)
        ->first();

    // Insert data into materials table and get the material_id
    $materialId = DB::table('materials')->insertGetId([
        'material_id' => $tempMaterial->material_id,
        'img' => $tempMaterial->img,
        'name' => $tempMaterial->name,
        'category' => $tempMaterial->category,
        'soldPer' => $tempMaterial ->soldPer,
    ]);

    // Insert data into materialdetails table and get the detail_id
    $detailId = DB::table('materialdetails')->insertGetId([
        'detail_id' => $tempMaterial->detail_id,
        'material_id' => $materialId,
        'size' => $tempMaterial->size,
        'type' => $tempMaterial->type,
        'color' => $tempMaterial->color,
        'other_details' => $tempMaterial->other_details,
    ]);

    // Insert data into inventory table
    DB::table('inventory')->insert([
        'inventory_id' => $tempId,
        'seller_id' => $tempMaterial->seller_id,
        'material_id' => $detailId, // Use detail_id as material_id in the inventory table
        'availability' => $tempMaterial->availability,
        'purchase_price' => $tempMaterial->purchase_price,
        'weeklySearch' => $tempMaterial->weeklySearch,
        'addedToCartWeekly' => $tempMaterial->addedToCartWeekly,
        'totalMonthlySearch' => $tempMaterial->totalMonthlySearch,
        'totalMonthToCart' => $tempMaterial->totalMonthlyToCart,
    ]);

    // Delete the record from tempmaterials table after successful transfer
    DB::table('tempmaterials')->where('temp_id', $tempId)->delete();

    // Return a success response
    return response()->json(['message' => 'Material approved and transferred successfully.'], 200);
}


public function getInactiveAccounts()
{
    $inactiveAccounts = DB::table('users')
        ->select('id','name', 'email', 'user_Type', 'account_status')
        ->where('account_status', '=', 'Deactivated')
        ->get();


    return response()->json($inactiveAccounts, 200);
}



public function reactivateAccount(Request $request)
{
    // Get the user ID from the URL parameter
    $userId = $request->input('id');

    // Reactivate the account using raw SQL query
    DB::update('UPDATE users SET account_status = ? WHERE id = ?', ['Active', $userId]);

    // Return a success response
    return response()->json(['message' => 'Account reactivated successfully'], 200);
}


public function orderPlacement(Request $request)
{
    try {
        $sellerId = $request->input('seller_id');
        $buyerId = $request->input('buyer_id');
        $inventoryId = $request->input('inventory_id');
        $orderAmount = $request->input('orderAmount');
        $days = $request->input('days'); // nullable input


        // Raw SQL query to insert data into the orderstable
        DB::insert('INSERT INTO orderstable (seller_id, buyer_id, inventory_id, orderAmount, days, approve, agreedPrice) VALUES (?, ?, ?, ?, ?,Null,Null)',
            [$sellerId, $buyerId, $inventoryId, $orderAmount, $days]);

        // Return a success response
        return response()->json(['message' => 'Order placed successfully'], 201);
    } catch (\Exception $e) {
        // Log the exception for debugging
        error_log($e->getMessage());
        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }
}



public function getSellerOrders(Request $request)
{
    // Retrieve the seller_id parameter from the URL
    $sellerId = $request->input('seller_id');

    $sellerOrders = DB::table('orderstable')
        ->join('inventory', 'orderstable.inventory_id', '=', 'inventory.inventory_id')
        ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
        ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
        ->join('buyers', 'orderstable.buyer_id', '=', 'buyers.buyer_id') // Join with buyers table
        ->join('users', 'buyers.user_id', '=', 'users.id') // Join with users table to get contact number
        ->select(
            'orderstable.order_id',
            'orderstable.orderAmount',
            'orderstable.days',
            'orderstable.approve',
            'materials.name as material_name',
            'materials.soldPer as per',
            'users.contact as contact',
            'users.name as name',
            DB::raw('(CASE WHEN orderstable.agreedPrice IS NOT NULL THEN orderstable.agreedPrice ELSE (inventory.purchase_price * orderstable.orderAmount) END) as price'), // Calculate price
            DB::raw('TO_BASE64(materials.img) as material_image') // Base64 encode the blob
        )
        ->where('orderstable.seller_id', $sellerId)
        ->get();

    return response()->json($sellerOrders, 200);
}


public function updateOrderStatus(Request $request)
{
    $orderId = $request->input('order_id');
    $status = $request->input('status'); // 'approve', 'reject', or 'toggle'

    $query = "UPDATE orderstable SET approve = ";
    
    if ($status === 'approve') {
        $query .= "true";
    } elseif ($status === 'reject') {
        $query .= "false";
    } elseif ($status === 'toggle') {
        $query .= "CASE WHEN approve IS NULL THEN true ELSE false END";
    } else {
        return response()->json(['success' => false, 'message' => 'Invalid status provided'], 400);
    }

    $query .= " WHERE order_id = :order_id";

    try {
        // Execute the raw SQL query
        DB::update($query, ['order_id' => $orderId]);

        return response()->json(['success' => true, 'message' => 'Order status updated successfully'], 200);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error updating order status'], 500);
    }
}


public function getOrders(Request $request)
{
    // Retrieve the buyer_id parameter from the URL
    $buyer_id = $request->input('buyer_id');
    
    try {
        $buyerOrders = DB::table('orderstable')
            ->where('buyer_id', $buyer_id)
            ->join('inventory', 'orderstable.inventory_id', '=', 'inventory.inventory_id')
            ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
            ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
            ->join('sellers', 'orderstable.seller_id', '=', 'sellers.seller_id') // Join with buyers table
            ->join('users', 'sellers.user_id', '=', 'users.id')  // Join with users table to get seller's data
            ->select(
                'users.name as seller_name',
                'users.address as seller_address',
                'users.contact as seller_contact',
                'materials.name as material_name',
                'orderstable.orderAmount as ordered_items',
                'materials.soldPer as per',
                'orderstable.order_id as id',
                'orderstable.days as days',
                DB::raw('(CASE WHEN orderstable.agreedPrice IS NOT NULL THEN orderstable.agreedPrice ELSE (inventory.purchase_price * orderstable.orderAmount) END) as total_price'), // Calculate total price
                'orderstable.approve as approval_status'
            )
            ->get();

        return response()->json($buyerOrders, 200);
    } catch (\Exception $e) {
        // Log the error for debugging purposes
        \Log::error($e);

        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }
}




public function deactivateAccount(Request $request)
{
    // Get the user ID from the URL parameter
    $userId = $request->input('id');

    // Reactivate the account using raw SQL query
    DB::update('UPDATE users SET account_status = ? WHERE id = ?', ['Deactivated', $userId]);

    // Return a success response
    return response()->json(['message' => 'Account Deactivated successfully'], 200);
}


public function checkAccountStatus(Request $request)
{
    $email = $request->input('email');

    // Retrieve the user by email and account status
    $user = DB::select("SELECT * FROM users WHERE email = ? AND TRIM(account_status) = 'Active' LIMIT 1", [$email]);

    // Check if the user with active account status exists
    if (!empty($user)) {
        return response()->json([
            'success' => true,
            'message' => 'Account is active'
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Account not found or not active'
    ]);
}


public function updateOrderDetails(Request $request)
{
    // Retrieve the order_id parameter from the request
    $order_id = $request->input('order_id');

    // Retrieve the new orderAmount and orderDays values from the request
    $newOrderAmount = $request->input('new_order_amount');
    $newOrderDays = $request->input('new_order_days');

    try {
        // Fetch existing order details
        $existingOrder = DB::table('orderstable')
            ->where('order_id', $order_id)
            ->first();

        // Update the order details in the database
        DB::table('orderstable')
            ->where('order_id', $order_id)
            ->update([
                'orderAmount' => $newOrderAmount != -1 ? $newOrderAmount : $existingOrder->orderAmount,
                'days' => $newOrderDays != -1 ? $newOrderDays : $existingOrder->days,
            ]);

        // You can also fetch and return the updated order details if needed
        $updatedOrder = DB::table('orderstable')
            ->where('order_id', $order_id)
            ->first();

        return response()->json($updatedOrder, 200);
    } catch (\Exception $e) {
        // Log the error for debugging purposes
        \Log::error($e);

        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }
}



public function adjustOrderPrice(Request $request)
{
    try {
        // Retrieve the order_id and new_price parameters from the request
        $orderId = $request->input('order_id');
        $newPrice = $request->input('new_price');

        // Fetch existing order details
        $existingOrder = DB::table('orderstable')
            ->where('order_id', $orderId)
            ->first();

        // Update the order details in the database
        DB::table('orderstable')
            ->where('order_id', $orderId)
            ->update([
                'agreedPrice' => $newPrice,
            ]);

        // You can also fetch and return the updated order details if needed
        $updatedOrder = DB::table('orderstable')
            ->where('order_id', $orderId)
            ->first();

        return response()->json($updatedOrder, 200);
    } catch (\Exception $e) {
        // Log the error for debugging purposes
        \Log::error($e);

        // Return an error response
        return response()->json(['error' => 'Internal Server Error'], 500);
    }
}



public function updateTemporaryInventoryItem(Request $request)
{
    // Validate the incoming request data
    $this->validate($request, [
        'temp_id' => 'required|exists:tempmaterials,temp_id',
        'availability' => 'nullable|string',
    ]);

    // Retrieve the request parameters
    $tempId = $request->input('temp_id');
    $availability = $request->input('availability');

    // Use raw SQL query to update the temporary inventory item
    $updatedRows = DB::update(
        'UPDATE tempmaterials 
         SET availability = ? 
         WHERE temp_id = ?',
        [$availability, $tempId]
    );

    if ($updatedRows > 0) {
        // Successfully updated the temporary inventory item
        return response()->json(['message' => 'Temporary inventory item updated successfully'], 200);
    } else {
        // No matching temporary inventory item found for the given temp_id
        return response()->json(['error' => 'Temporary inventory item not found'], 404);
    }
}

// public function reverseGeocode(Request $request)
// {
//     $sellerAddress = $request->input('seller_address');

//     $encodedSellerAddress = urlencode($sellerAddress);

//     // Construct the URL for the Nominatim API
//     $url = "https://nominatim.openstreetmap.org/search?q=$encodedSellerAddress&format=json&limit=1&countrycodes=PH";

//     // Create a new Guzzle client
//     $client = new \GuzzleHttp\Client([
//         'curl' => [
//             CURLOPT_SSL_VERIFYPEER => false,
//         ],
//     ]);

//     // Send a GET request to the Nominatim API
//     $response = $client->get($url);

//     // Check if the request was successful
//     if ($response->getStatusCode() === 200) {
//         // Parse the JSON response
//         $data = json_decode($response->getBody(), true);

//         // Check if there is at least one result
//         if (!empty($data)) {
//             // Extract latitude and longitude from the first result
//             $latitude = $data[0]['lat'];
//             $longitude = $data[0]['lon'];

//             // Log latitude and longitude separately
//             \Log::info("Latitude: $latitude, Longitude: $longitude");

//             // Return the latitude and longitude as an associative array
//             return ['latitude' => $latitude, 'longitude' => $longitude];
//         }
//     }

//     // If the request failed or there were no results, return null
//     return null;
// }

}









