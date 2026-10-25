<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Written to say what is wrong and how to fix it. :attribute is replaced
    | with the field's Arabic name from 'attributes' below.
    |
    */

    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute حين يكون :other هو :value.',
    'active_url' => ':attribute ليس رابطًا صالحًا.',
    'after' => ':attribute يجب أن يكون بعد :date.',
    'after_or_equal' => ':attribute يجب أن يكون في :date أو بعده.',
    'alpha' => ':attribute يقبل الحروف فقط.',
    'alpha_dash' => ':attribute يقبل الحروف والأرقام والشرطات فقط.',
    'alpha_num' => ':attribute يقبل الحروف والأرقام فقط.',
    'any_of' => 'قيمة :attribute غير صالحة.',
    'array' => ':attribute يجب أن يكون قائمة.',
    'array_keys' => ':attribute يجب أن يحتوي على المفاتيح: :values.',
    'ascii' => ':attribute يقبل الحروف والرموز الإنجليزية فقط.',
    'before' => ':attribute يجب أن يكون قبل :date.',
    'before_or_equal' => ':attribute يجب أن يكون في :date أو قبله.',
    'between' => [
        'array' => ':attribute يجب أن يحتوي بين :min و:max عناصر.',
        'file' => 'حجم :attribute يجب أن يكون بين :min و:max كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون بين :min و:max.',
        'string' => ':attribute يجب أن يكون بين :min و:max حرفًا.',
    ],
    'boolean' => ':attribute يجب أن يكون نعم أو لا.',
    'can' => ':attribute فيه قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد :attribute لا يطابقه.',
    'contains' => ':attribute ينقصه قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => ':attribute ليس تاريخًا صالحًا.',
    'date_equals' => ':attribute يجب أن يكون :date.',
    'date_format' => ':attribute لا يطابق الصيغة :format.',
    'decimal' => ':attribute يجب أن يحتوي :decimal منازل عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute حين يكون :other هو :value.',
    'different' => ':attribute و:other يجب أن يختلفا.',
    'digits' => ':attribute يجب أن يكون :digits أرقام.',
    'digits_between' => ':attribute يجب أن يكون بين :min و:max أرقام.',
    'dimensions' => 'أبعاد :attribute غير مناسبة.',
    'distinct' => ':attribute فيه قيمة مكررة.',
    'doesnt_contain' => ':attribute يجب ألا يحتوي على: :values.',
    'doesnt_end_with' => ':attribute يجب ألا ينتهي بأحد هذه: :values.',
    'doesnt_start_with' => ':attribute يجب ألا يبدأ بأحد هذه: :values.',
    'email' => ':attribute ليس بريدًا إلكترونيًا صالحًا.',
    'encoding' => ':attribute يجب أن يكون بترميز :encoding.',
    'ends_with' => ':attribute يجب أن ينتهي بأحد هذه: :values.',
    'enum' => 'قيمة :attribute غير صالحة.',
    'exists' => 'قيمة :attribute غير موجودة.',
    'extensions' => ':attribute يجب أن يكون من نوع: :values.',
    'file' => ':attribute يجب أن يكون ملفًا.',
    'filled' => ':attribute لا يكون فارغًا.',
    'gt' => [
        'array' => ':attribute يجب أن يحتوي أكثر من :value عناصر.',
        'file' => 'حجم :attribute يجب أن يزيد على :value كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون أكبر من :value.',
        'string' => ':attribute يجب أن يزيد على :value حرفًا.',
    ],
    'gte' => [
        'array' => ':attribute يجب أن يحتوي :value عناصر أو أكثر.',
        'file' => 'حجم :attribute يجب أن يكون :value كيلوبايت أو أكثر.',
        'numeric' => ':attribute يجب أن يكون :value أو أكثر.',
        'string' => ':attribute يجب أن يكون :value حرفًا أو أكثر.',
    ],
    'hex_color' => ':attribute ليس لونًا صالحًا.',
    'image' => ':attribute يجب أن يكون صورة.',
    'in' => 'قيمة :attribute غير صالحة.',
    'in_array' => ':attribute يجب أن يكون من :other.',
    'in_array_keys' => ':attribute يجب أن يحتوي أحد هذه المفاتيح: :values.',
    'integer' => ':attribute يجب أن يكون رقمًا صحيحًا.',
    'ip' => ':attribute ليس عنوان IP صالحًا.',
    'ipv4' => ':attribute ليس عنوان IPv4 صالحًا.',
    'ipv6' => ':attribute ليس عنوان IPv6 صالحًا.',
    'json' => ':attribute ليس JSON صالحًا.',
    'list' => ':attribute يجب أن يكون قائمة.',
    'lowercase' => ':attribute يجب أن يكون بحروف صغيرة.',
    'lt' => [
        'array' => ':attribute يجب أن يحتوي أقل من :value عناصر.',
        'file' => 'حجم :attribute يجب أن يقل عن :value كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون أقل من :value.',
        'string' => ':attribute يجب أن يقل عن :value حرفًا.',
    ],
    'lte' => [
        'array' => ':attribute يجب ألا يحتوي أكثر من :value عناصر.',
        'file' => 'حجم :attribute يجب ألا يزيد على :value كيلوبايت.',
        'numeric' => ':attribute يجب ألا يزيد على :value.',
        'string' => ':attribute يجب ألا يزيد على :value حرفًا.',
    ],
    'mac_address' => ':attribute ليس عنوان MAC صالحًا.',
    'max' => [
        'array' => ':attribute يجب ألا يحتوي أكثر من :max عناصر.',
        'file' => 'حجم :attribute يجب ألا يزيد على :max كيلوبايت.',
        'numeric' => ':attribute يجب ألا يزيد على :max.',
        'string' => ':attribute يجب ألا يزيد على :max حرفًا.',
    ],
    'max_digits' => ':attribute يجب ألا يزيد على :max أرقام.',
    'mimes' => ':attribute يجب أن يكون ملفًا من نوع: :values.',
    'mimetypes' => ':attribute يجب أن يكون ملفًا من نوع: :values.',
    'min' => [
        'array' => ':attribute يجب أن يحتوي :min عناصر على الأقل.',
        'file' => 'حجم :attribute يجب أن يكون :min كيلوبايت على الأقل.',
        'numeric' => ':attribute يجب أن يكون :min على الأقل.',
        'string' => ':attribute يجب أن يكون :min أحرف على الأقل.',
    ],
    'min_digits' => ':attribute يجب أن يكون :min أرقام على الأقل.',
    'missing' => ':attribute يجب ألا يُرسل.',
    'missing_if' => ':attribute يجب ألا يُرسل حين يكون :other هو :value.',
    'missing_unless' => ':attribute يجب ألا يُرسل إلا إذا كان :other هو :value.',
    'missing_with' => ':attribute يجب ألا يُرسل مع :values.',
    'missing_with_all' => ':attribute يجب ألا يُرسل مع :values.',
    'multiple_of' => ':attribute يجب أن يكون من مضاعفات :value.',
    'not_in' => 'قيمة :attribute غير صالحة.',
    'not_regex' => 'صيغة :attribute غير صالحة.',
    'numeric' => ':attribute يجب أن يكون رقمًا.',
    'password' => [
        'letters' => ':attribute يجب أن يحتوي حرفًا واحدًا على الأقل.',
        'mixed' => ':attribute يجب أن يحتوي حرفًا كبيرًا وحرفًا صغيرًا على الأقل.',
        'numbers' => ':attribute يجب أن يحتوي رقمًا واحدًا على الأقل.',
        'symbols' => ':attribute يجب أن يحتوي رمزًا واحدًا على الأقل.',
        'uncompromised' => 'ظهرت :attribute هذه في تسريب بيانات. اختر غيرها.',
    ],
    'present' => ':attribute يجب أن يُرسل.',
    'present_if' => ':attribute يجب أن يُرسل حين يكون :other هو :value.',
    'present_unless' => ':attribute يجب أن يُرسل إلا إذا كان :other هو :value.',
    'present_with' => ':attribute يجب أن يُرسل مع :values.',
    'present_with_all' => ':attribute يجب أن يُرسل مع :values.',
    'prohibited' => ':attribute غير مسموح به.',
    'prohibited_if' => ':attribute غير مسموح به حين يكون :other هو :value.',
    'prohibited_if_accepted' => ':attribute غير مسموح به حين يُقبل :other.',
    'prohibited_if_declined' => ':attribute غير مسموح به حين يُرفض :other.',
    'prohibited_unless' => ':attribute غير مسموح به إلا إذا كان :other من :values.',
    'prohibits' => ':attribute يمنع إرسال :other معه.',
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => ':attribute مطلوب.',
    'required_array_keys' => ':attribute يجب أن يحتوي: :values.',
    'required_if' => ':attribute مطلوب حين يكون :other هو :value.',
    'required_if_accepted' => ':attribute مطلوب حين يُقبل :other.',
    'required_if_declined' => ':attribute مطلوب حين يُرفض :other.',
    'required_unless' => ':attribute مطلوب إلا إذا كان :other من :values.',
    'required_with' => ':attribute مطلوب مع :values.',
    'required_with_all' => ':attribute مطلوب مع :values.',
    'required_without' => ':attribute مطلوب حين لا يوجد :values.',
    'required_without_all' => ':attribute مطلوب حين لا يوجد أي من :values.',
    'same' => ':attribute و:other يجب أن يتطابقا.',
    'size' => [
        'array' => ':attribute يجب أن يحتوي :size عناصر.',
        'file' => 'حجم :attribute يجب أن يكون :size كيلوبايت.',
        'numeric' => ':attribute يجب أن يكون :size.',
        'string' => ':attribute يجب أن يكون :size حرفًا.',
    ],
    'starts_with' => ':attribute يجب أن يبدأ بأحد هذه: :values.',
    'string' => ':attribute يجب أن يكون نصًا.',
    'timezone' => ':attribute ليس منطقة زمنية صالحة.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'تعذّر رفع :attribute. حاول مرة أخرى.',
    'uppercase' => ':attribute يجب أن يكون بحروف كبيرة.',
    'url' => ':attribute ليس رابطًا صالحًا.',
    'ulid' => ':attribute ليس معرّف ULID صالحًا.',
    'uuid' => ':attribute ليس معرّف UUID صالحًا.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'code' => 'رمز التحقق',
        'recovery_code' => 'رمز الاسترداد',
        'phone' => 'رقم الهاتف',
    ],

];
