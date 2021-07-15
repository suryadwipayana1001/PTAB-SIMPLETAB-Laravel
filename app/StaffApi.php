<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StaffApi extends Model
{
    protected $table = 'staffs';
    protected $fillable = [
        'code',
        'name',
        'phone',
        'dapertement_id'
    ];

    public function dapertement() { 
        return $this->belongsTo('App\Dapertement')->select('id', 'name'); 
    }

    public function action()
    {
        return $this->belongsToMany(Action::class, 'action_staff', 'action_id', 'staff_id')
            ->withPivot([
                'status'
            ]);
    }
}
