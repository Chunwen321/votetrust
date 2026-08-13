const countryByCallingCode = {
    20: 'EG',
    27: 'ZA',
    30: 'GR',
    31: 'NL',
    32: 'BE',
    33: 'FR',
    34: 'ES',
    36: 'HU',
    40: 'RO',
    41: 'CH',
    43: 'AT',
    44: 'GB',
    45: 'DK',
    46: 'SE',
    47: 'NO',
    48: 'PL',
    49: 'DE',
    51: 'PE',
    52: 'MX',
    53: 'CU',
    54: 'AR',
    55: 'BR',
    56: 'CL',
    57: 'CO',
    58: 'VE',
    60: 'MY',
    61: 'AU',
    62: 'ID',
    63: 'PH',
    64: 'NZ',
    65: 'SG',
    66: 'TH',
    81: 'JP',
    82: 'KR',
    84: 'VN',
    86: 'CN',
    90: 'TR',
    91: 'IN',
    92: 'PK',
    93: 'AF',
    94: 'LK',
    95: 'MM',
    98: 'IR',
    212: 'MA',
    213: 'DZ',
    216: 'TN',
    218: 'LY',
    234: 'NG',
    254: 'KE',
    255: 'TZ',
    256: 'UG',
    351: 'PT',
    352: 'LU',
    353: 'IE',
    354: 'IS',
    355: 'AL',
    356: 'MT',
    357: 'CY',
    358: 'FI',
    359: 'BG',
    370: 'LT',
    371: 'LV',
    372: 'EE',
    373: 'MD',
    374: 'AM',
    375: 'BY',
    376: 'AD',
    377: 'MC',
    378: 'SM',
    380: 'UA',
    381: 'RS',
    382: 'ME',
    385: 'HR',
    386: 'SI',
    387: 'BA',
    389: 'MK',
    420: 'CZ',
    421: 'SK',
    423: 'LI',
    502: 'GT',
    503: 'SV',
    504: 'HN',
    505: 'NI',
    506: 'CR',
    507: 'PA',
    591: 'BO',
    592: 'GY',
    593: 'EC',
    595: 'PY',
    597: 'SR',
    598: 'UY',
    670: 'TL',
    673: 'BN',
    675: 'PG',
    679: 'FJ',
    685: 'WS',
    852: 'HK',
    853: 'MO',
    855: 'KH',
    856: 'LA',
    880: 'BD',
    886: 'TW',
    960: 'MV',
    961: 'LB',
    962: 'JO',
    963: 'SY',
    964: 'IQ',
    965: 'KW',
    966: 'SA',
    967: 'YE',
    968: 'OM',
    970: 'PS',
    971: 'AE',
    972: 'IL',
    973: 'BH',
    974: 'QA',
    975: 'BT',
    976: 'MN',
    977: 'NP',
    992: 'TJ',
    993: 'TM',
    994: 'AZ',
    995: 'GE',
    996: 'KG',
    998: 'UZ',
};

const callingCodes = Object.keys(countryByCallingCode).sort((left, right) => right.length - left.length);

export const countryCodeFromPhoneNumber = (phoneNumber) => {
    const compact = String(phoneNumber).replace(/[().\-\s]/g, '');
    const internationalDigits = compact.startsWith('+')
        ? compact.slice(1)
        : compact.startsWith('00') ? compact.slice(2) : null;

    if (internationalDigits === null || !/^\d+$/.test(internationalDigits)) {
        return null;
    }

    const callingCode = callingCodes.find((code) => internationalDigits.startsWith(code));

    return callingCode ? countryByCallingCode[callingCode] : null;
};

export const initializePhoneCountryDetection = () => {
    const phoneNumber = document.querySelector('[data-phone-number]');
    const nationality = document.querySelector('[data-nationality-select]');

    if (!phoneNumber || !nationality) {
        return;
    }

    let nationalityWasManuallySelected = false;

    nationality.addEventListener('change', (event) => {
        if (event.isTrusted) {
            nationalityWasManuallySelected = true;
        }
    });

    const detectCountry = () => {
        const enteredValue = phoneNumber.value.trimStart();

        if (enteredValue !== '') {
            phoneNumber.value = enteredValue.startsWith('00')
                ? `+${enteredValue.slice(2)}`
                : `+${enteredValue.replace(/\+/g, '')}`;
        }

        if (nationalityWasManuallySelected) {
            return;
        }

        const detectedCountry = countryCodeFromPhoneNumber(phoneNumber.value);
        const matchingOption = detectedCountry
            ? nationality.querySelector(`option[value="${detectedCountry}"]`)
            : null;

        if (!matchingOption || nationality.value === detectedCountry) {
            return;
        }

        nationality.value = detectedCountry;
        nationality.dispatchEvent(new Event('change', { bubbles: true }));
    };

    phoneNumber.addEventListener('input', detectCountry);
    phoneNumber.addEventListener('change', detectCountry);
};
