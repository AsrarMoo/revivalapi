<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * تعيين المفتاح الرئيسي (Primary Key) ليكون 'user_id' بدلاً من 'id'.
     */
    protected $primaryKey = 'user_id';

    /**
     * تحديد أن المفتاح الرئيسي هو auto-increment.
     */
    public $incrementing = true;

    /**
     * الحقول القابلة للملء.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',        // اسم المستخدم
        'password',    // كلمة المرور
        'user_type',   // نوع المستخدم
        'is_active',   // حالة الحساب
    ];

    /**
     * الحقول المخفية عند التسلسل.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',  // إخفاء كلمة المرور
        'remember_token',
    ];

    /**
     * الحقول التي يجب تحويلها إلى نوع آخر.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * تشفير كلمة المرور تلقائيًا عند الحفظ أو التحديث.
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * العلاقة بين المستخدم والطبيب (كل طبيب لديه حساب مستخدم).
     */
    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'user_id', 'user_id');
    }

    /**
     * العلاقة بين المستخدم والمريض (كل مريض لديه حساب مستخدم).
     */
    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id', 'user_id');
    }

    /**
     * العلاقة بين المستخدم والمستشفى (كل مستشفى لديه حساب مستخدم).
     */
    public function hospital()
    {
        return $this->hasOne(Hospital::class, 'user_id', 'user_id');
    }
}
