<?php

namespace App\Modules\People\Data;

final readonly class MemberProfileView
{
    /**
     * @param  array{type:string,countryCode:string,masked:string}  $identifier
     * @param  array{email:?string,phone:?string,version:int}  $contact
     * @param  array<string, mixed>|null  $address
     */
    public function __construct(
        public string $personId,
        public string $givenName,
        public string $familyName,
        public int $version,
        public array $identifier,
        public array $contact,
        public ?array $address,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'personId' => $this->personId,
            'givenName' => $this->givenName,
            'familyName' => $this->familyName,
            'version' => $this->version,
            'identifier' => $this->identifier,
            'contact' => $this->contact,
            'address' => $this->address,
        ];
    }
}
