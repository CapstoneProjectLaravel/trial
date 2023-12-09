<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\MaterialDetail;
use App\Models\Inventory;

class InventyAPI extends Controller
{
    // Create a new material
    public function storeMaterial(Request $request)
    {
        // Validate the incoming request data
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

    // Create a new inventory item
    public function storeInventory(Request $request)
    {
        // Validate the incoming request data
        $this->validate($request, [
            'seller_id' => 'required|integer',
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|integer',
            'purchase_price' => 'required|numeric',
        ]);
    
        // Check if a record with the same material_id and seller_id already exists
        $existingRecord = Inventory::where('seller_id', $request->input('seller_id'))
                                    ->where('material_id', $request->input('material_id'))
                                    ->first();
    
        if ($existingRecord) {
            // Record already exists, return an error response
            return response()->json(['error' => 'Duplicate material_id for the same seller.'], 400);
        }
    
        // Create a new inventory item
        $inventory = new Inventory();
        $inventory->seller_id = $request->input('seller_id');
        $inventory->material_id = $request->input('material_id');
        $inventory->quantity = $request->input('quantity');
        $inventory->purchase_price = $request->input('purchase_price');
        $inventory->save();
    
        return response()->json($inventory, 201);
    }
    

    public function getMaterials()
    {
        // Retrieve all materials
        $material = materials::distinct('name')->pluck('name');

        return response()->json($material, 200);
    }

    public function getTypes()
    {
        // Retrieve distinct types from MaterialDetail
        $types = materialdetails::distinct('type')->pluck('type');
        return response()->json($types, 200);
    }

    public function getSizes()
    {
        // Retrieve distinct sizes from MaterialDetail
        $sizes = materialdetails::distinct('size')->pluck('size');
        return response()->json($sizes, 200);
    }

    public function getSellerInventory($seller_id)
{
    $inventoryItems = DB::table('inventory')
    ->join('materialdetails', 'inventory.material_id', '=', 'materialdetails.detail_id')
    ->join('materials', 'materialdetails.material_id', '=', 'materials.material_id')
    ->select(
        'inventory.*',
        'materialdetails.size',
        'materialdetails.type',
        'materialdetails.color',
        'materialdetails.other_details',
        'materials.img as material_image'
    )
    ->where('inventory.seller_id', $seller_id) // Use 'inventory.seller_id' instead of 'inventories.seller_id'
    ->get();

return response()->json($inventoryItems, 200);

}


   
}


