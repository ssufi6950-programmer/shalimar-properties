<?php
namespace App\Services;

/** Validate only the deliberately selected public business contact, never private ERP mobile. */
final class ListingSellerContact
{
    public static function requireBusiness(array $contact):array
    {
        $phone=PublicListingContact::phone($contact['mobile']??$contact['phone']??'',true);
        if(!$phone)throw new \InvalidArgumentException('The selected representative needs a valid public business mobile. Choose another representative or ask support to correct the directory.');
        $email=PublicContactEmail::optional($contact['email']??'');
        return ['phone'=>$phone,'email'=>$email];
    }
}
