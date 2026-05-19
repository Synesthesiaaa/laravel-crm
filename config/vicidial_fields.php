<?php

return [
    'fields' => [
        'first_name' => ['label' => 'First name', 'writeable' => true],
        'middle_initial' => ['label' => 'Middle initial', 'writeable' => true],
        'last_name' => ['label' => 'Last name', 'writeable' => true],
        'address1' => ['label' => 'Address 1', 'writeable' => true],
        'address2' => ['label' => 'Address 2', 'writeable' => true],
        'address3' => ['label' => 'Address 3', 'writeable' => true],
        'city' => ['label' => 'City', 'writeable' => true],
        'state' => ['label' => 'State', 'writeable' => true],
        'province' => ['label' => 'Province', 'writeable' => true],
        'postal_code' => ['label' => 'Postal code', 'writeable' => true],
        'country_code' => ['label' => 'Country code', 'writeable' => true],
        'gender' => ['label' => 'Gender', 'writeable' => true],
        'date_of_birth' => ['label' => 'Date of birth', 'writeable' => true],
        'phone_number' => ['label' => 'Phone number', 'writeable' => true],
        'phone_code' => ['label' => 'Phone code', 'writeable' => true],
        'alt_phone' => ['label' => 'Alt phone', 'writeable' => true],
        'email' => ['label' => 'Email', 'writeable' => true],
        'comments' => ['label' => 'Comments', 'writeable' => true],
        'security_phrase' => ['label' => 'Security phrase', 'writeable' => true],
        'source_id' => ['label' => 'Source ID', 'writeable' => true],
        'vendor_lead_code' => ['label' => 'Vendor lead code', 'writeable' => true],
        'lead_id' => ['label' => 'Lead ID', 'writeable' => false],
        'list_id' => ['label' => 'List ID', 'writeable' => false],
        'status' => ['label' => 'Status', 'writeable' => false],
    ],
    'aliases' => [
        'first_name' => [
            'fname',
            'firstname',
            'customer_first_name',
            'cust_first_name',
            'given_name',
        ],
        'last_name' => [
            'lname',
            'lastname',
            'customer_last_name',
            'cust_last_name',
            'surname',
            'family_name',
        ],
        'email' => [
            'email_address',
            'customer_email',
            'cust_email',
            'e_mail',
        ],
        'phone_number' => [
            'phone',
            'mobile',
            'mobile_number',
            'contact_number',
        ],
        'alt_phone' => [
            'alternate_phone',
            'secondary_phone',
        ],
        'address1' => [
            'address',
            'street',
            'street_address',
            'addr1',
        ],
        'address2' => [
            'addr2',
        ],
        'city' => [
            'town',
        ],
        'state' => [
            'province_state',
            'region',
        ],
        'postal_code' => [
            'zip',
            'zipcode',
            'postcode',
        ],
        'date_of_birth' => [
            'dob',
            'birthdate',
            'birthday',
        ],
        'comments' => [
            'notes',
            'remarks',
        ],
        'gender' => [
            'sex',
        ],
    ],
];
