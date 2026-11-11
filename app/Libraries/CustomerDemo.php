<?php

namespace App\Libraries;

/** Synthetic records only. This module never reads the website's production stores. */
final class CustomerDemo
{
    public static function customer(): array
    {
        return ['name' => 'Ahmed Raza', 'firstName' => 'Ahmed', 'cnic' => '*****-*******-*', 'mobile' => '0300-1234567', 'email' => 'ahmed.raza@example.com', 'erp' => 'Shalimar / Zalmi', 'verified' => '04 Nov 2026', 'city' => 'Sargodha'];
    }

    public static function properties(): array
    {
        return [
            ['id'=>'SP-10481','project'=>'Shalimar Smart City','image'=>'ssc-aerial-live.webp','location'=>'Lahore Road, Sargodha','erp'=>'Shalimar','plot'=>'Plot 124','size'=>'5 Marla','block'=>'A','sector'=>'Residential','type'=>'Residential Plot','booking'=>'BK-20841','ownership'=>'Verified','eligible'=>true,'reason'=>'','listing'=>null],
            ['id'=>'SP-10482','project'=>'Gulberg City','image'=>'gc-villas.webp','location'=>'Faisalabad Road, Sargodha','erp'=>'Shalimar','plot'=>'Plot 218','size'=>'10 Marla','block'=>'B','sector'=>'Residential','type'=>'Residential Plot','booking'=>'BK-20842','ownership'=>'Verified','eligible'=>true,'reason'=>'','listing'=>'RSL-2401'],
            ['id'=>'SP-10483','project'=>'Zalmi X','image'=>'zx-building.webp','location'=>'University Road, Sargodha','erp'=>'Zalmi','plot'=>'Office 12','size'=>'650 sq. ft.','block'=>'Tower A','sector'=>'Business','type'=>'Commercial Office','booking'=>'BK-20843','ownership'=>'Verified','eligible'=>true,'reason'=>'','listing'=>null],
            ['id'=>'SP-10484','project'=>'Shalimar Smart City','image'=>'ssc-business-district.webp','location'=>'Lahore Road, Sargodha','erp'=>'Shalimar','plot'=>'File 408','size'=>'7 Marla','block'=>'C','sector'=>'Residential','type'=>'Residential File','booking'=>'BK-20844','ownership'=>'Transfer Pending','eligible'=>false,'reason'=>'Your ownership transfer is being verified. Resale will be available after verification.','listing'=>null],
            ['id'=>'SP-10485','project'=>'Gulberg City','image'=>'gc-boulevard.webp','location'=>'Faisalabad Road, Sargodha','erp'=>'Shalimar','plot'=>'Plot 309','size'=>'10 Marla','block'=>'C','sector'=>'Residential','type'=>'Residential Plot','booking'=>'BK-20845','ownership'=>'Verified','eligible'=>true,'reason'=>'','listing'=>null],
            ['id'=>'SP-10486','project'=>'Shalimar Smart City','image'=>'ssc-business-district.webp','location'=>'Lahore Road, Sargodha','erp'=>'Shalimar','plot'=>'Plot 221','size'=>'4 Marla','block'=>'Business Bay','sector'=>'Commercial','type'=>'Commercial Plot','booking'=>'BK-20846','ownership'=>'Verified','eligible'=>true,'reason'=>'','listing'=>'RSL-2402'],
        ];
    }

    public static function listings(): array
    {
        return [
            ['id'=>'RSL-2401','propertyId'=>'SP-10482','project'=>'Gulberg City','plot'=>'Plot 218','size'=>'10 Marla','image'=>'gc-villas.webp','price'=>12500000,'method'=>'agent','assignee'=>'AG-001','status'=>'Active','date'=>'2026-11-02','remarks'=>'Please contact the assigned representative to arrange a visit.','inquiries'=>3],
            ['id'=>'RSL-2402','propertyId'=>'SP-10486','project'=>'Shalimar Smart City','plot'=>'Plot 221','size'=>'4 Marla','image'=>'ssc-business-district.webp','price'=>8400000,'method'=>'agency','assignee'=>'AY-001','status'=>'Pending Approval','date'=>'2026-11-04','remarks'=>'Commercial plot in Business Bay.','inquiries'=>0],
            ['id'=>'RSL-2318','propertyId'=>'SP-09912','project'=>'Shalimar Smart City','plot'=>'Plot 92','size'=>'5 Marla','image'=>'ssc-aerial-live.webp','price'=>4700000,'method'=>'self','assignee'=>null,'status'=>'Sold','date'=>'2026-09-12','remarks'=>'Historical demo listing. Ownership has been transferred.','inquiries'=>2],
            ['id'=>'RSL-2345','propertyId'=>'SP-10485','project'=>'Gulberg City','plot'=>'Plot 309','size'=>'10 Marla','image'=>'gc-boulevard.webp','price'=>13800000,'method'=>'self','assignee'=>null,'status'=>'Rejected','date'=>'2026-10-18','remarks'=>'Previous demo submission; ownership documents needed review.','inquiries'=>0],
            ['id'=>'RSL-2360','propertyId'=>'SP-10481','project'=>'Shalimar Smart City','plot'=>'Plot 124','size'=>'5 Marla','image'=>'ssc-aerial-live.webp','price'=>5200000,'method'=>'agency','assignee'=>'AY-002','status'=>'Cancelled','date'=>'2026-10-24','remarks'=>'Previous listing withdrawn by the owner.','inquiries'=>0],
        ];
    }

    public static function agents(): array
    {
        return [
            ['id'=>'AG-001','name'=>'Muhammad Adeel','initials'=>'MA','role'=>'Shalimar Representative','phone'=>'+92 300 555 0101','email'=>'adeel@example.com','specialty'=>'Residential properties','erp'=>['Shalimar']],
            ['id'=>'AG-002','name'=>'Sana Iqbal','initials'=>'SI','role'=>'Shalimar Representative','phone'=>'+92 321 555 0102','email'=>'sana@example.com','specialty'=>'Residential & commercial','erp'=>['Shalimar','Zalmi']],
            ['id'=>'AG-003','name'=>'Hamza Sheikh','initials'=>'HS','role'=>'Shalimar Representative','phone'=>'+92 333 555 0103','email'=>'hamza@example.com','specialty'=>'Commercial properties','erp'=>['Zalmi']],
        ];
    }

    public static function agencies(): array
    {
        return [
            ['id'=>'AY-001','name'=>'City Estate Partners','initials'=>'CE','role'=>'Authorized Agency','person'=>'Asad Mahmood','phone'=>'+92 300 555 0201','specialty'=>'Sargodha residential & commercial','erp'=>['Shalimar','Zalmi']],
            ['id'=>'AY-002','name'=>'Prime Property Group','initials'=>'PP','role'=>'Authorized Agency','person'=>'Nabeel Akram','phone'=>'+92 321 555 0202','specialty'=>'Residential communities','erp'=>['Shalimar']],
        ];
    }

    public static function activity(): array
    {
        return [
            ['icon'=>'send','title'=>'Your resale listing is under review','text'=>'Plot 221, Shalimar Smart City · RSL-2402','time'=>'Today, 09:30 AM','tone'=>'amber','url'=>'RSL-2402'],
            ['icon'=>'chat-dots','title'=>'A new buyer inquiry','text'=>'Your representative is following up on Gulberg City.','time'=>'Yesterday, 03:45 PM','tone'=>'blue','url'=>'RSL-2401'],
            ['icon'=>'check2-circle','title'=>'Your listing is now active','text'=>'Plot 218, Gulberg City is open for inquiries.','time'=>'02 Nov, 11:20 AM','tone'=>'green','url'=>'RSL-2401'],
            ['icon'=>'shield-check','title'=>'Ownership details verified','text'=>'Your Shalimar Smart City records are up to date.','time'=>'01 Nov, 10:10 AM','tone'=>'teal','url'=>null],
        ];
    }

    public static function all(): array
    {
        return ['customer'=>self::customer(),'properties'=>self::properties(),'listings'=>self::listings(),'agents'=>self::agents(),'agencies'=>self::agencies(),'activity'=>self::activity()];
    }

    public static function findProperty(string $id): ?array
    {
        foreach (self::properties() as $row) {
            if ($row['id'] === $id) return $row;
        }
        return null;
    }
}
