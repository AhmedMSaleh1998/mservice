<?php

namespace Modules\Users\Dto;

class RegisterDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $nationalId,
        public readonly string $regNumber,
        public readonly string $password,
    )
    {
    }

    public static function fromRequest($request): self
    {
        return new self(
            name: $request->input('name'),
            email: $request->input('email'),
            phone: $request->input('phone'),
            nationalId: $request->input('national_id'),
            regNumber: $request->input('reg_number'),
            password: $request->input('password'),
        );
    }
}
