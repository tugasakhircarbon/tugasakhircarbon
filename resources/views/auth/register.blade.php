<x-guest-layout>
<form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- nomor kartu keluarga -->
        <div class="mt-4">
            <x-input-label for="nomor_kartu_keluarga" :value="__('Nomor Kartu Keluarga')" />
            <x-text-input id="nomor_kartu_keluarga" class="block mt-1 w-full" type="text" name="nomor_kartu_keluarga" :value="old('nomor_kartu_keluarga')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('nomor_kartu_keluarga')" class="mt-2" />
        </div>

        <!-- NIK E-KTP -->
        <div class="mt-4">
            <x-input-label for="nik_e_ktp" :value="__('NIK E-KTP')" />
            <x-text-input id="nik_e_ktp" class="block mt-1 w-full" type="text" name="nik_e_ktp" :value="old('nik_e_ktp')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('nik_e_ktp')" class="mt-2" />
        </div>

        
        <!-- Phone Number -->
        <div class="mt-4">
            <x-input-label for="phone_number" :value="__('Phone Number')" />
            <x-text-input id="phone_number" class="block mt-1 w-full" type="text" name="phone_number" :value="old('phone_number')" autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
        </div>

        <!-- Address -->
        <div class="mt-4">
            <x-input-label for="address" :value="__('Address')" />
            <textarea id="address" class="block mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" name="address" autocomplete="street-address">{{ old('address') }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>
        
        <!-- Bank Name -->
        <div class="mt-4">
            <x-input-label for="bank_name" :value="__('Bank Name')" />
            <select id="bank_name" name="bank_name" class="block mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('Select a bank') }}</option>
                @foreach(app(\App\Services\MidtransService::class)->getSupportedBanks() as $code => $name)
                    <option value="{{ $code }}" {{ old('bank_name') == $code ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
        </div>

        <!-- Bank Account -->
        <div class="mt-4">
            <x-input-label for="bank_account" :value="__('Bank Account')" />
            <x-text-input id="bank_account" class="block mt-1 w-full" type="text" name="bank_account" :value="old('bank_account')" autocomplete="off" />
            <x-input-error :messages="$errors->get('bank_account')" class="mt-2" />
        </div>
            
        <!-- Password -->
            
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
    
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
    
        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
    
            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
    
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
            
            <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>
            
            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>