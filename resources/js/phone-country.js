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

const callingCodeByCountry = {
    AD: '376', AE: '971', AF: '93', AG: '1268', AI: '1264', AL: '355', AM: '374', AO: '244', AQ: '672', AR: '54', AS: '1684', AT: '43', AU: '61', AW: '297', AX: '358', AZ: '994',
    BA: '387', BB: '1246', BD: '880', BE: '32', BF: '226', BG: '359', BH: '973', BI: '257', BJ: '229', BL: '590', BM: '1441', BN: '673', BO: '591', BQ: '599', BR: '55', BS: '1242', BT: '975', BW: '267', BY: '375', BZ: '501',
    CA: '1', CC: '61', CD: '243', CF: '236', CG: '242', CH: '41', CI: '225', CK: '682', CL: '56', CM: '237', CN: '86', CO: '57', CR: '506', CU: '53', CV: '238', CW: '599', CX: '61', CY: '357', CZ: '420',
    DE: '49', DJ: '253', DK: '45', DM: '1767', DO: '1809', DZ: '213',
    EC: '593', EE: '372', EG: '20', EH: '212', ER: '291', ES: '34', ET: '251',
    FI: '358', FJ: '679', FK: '500', FM: '691', FO: '298', FR: '33',
    GA: '241', GB: '44', GD: '1473', GE: '995', GF: '594', GG: '44', GH: '233', GI: '350', GL: '299', GM: '220', GN: '224', GP: '590', GQ: '240', GR: '30', GS: '500', GT: '502', GU: '1671', GW: '245', GY: '592',
    HK: '852', HM: '672', HN: '504', HR: '385', HT: '509', HU: '36',
    ID: '62', IE: '353', IL: '972', IM: '44', IN: '91', IO: '246', IQ: '964', IR: '98', IS: '354', IT: '39',
    JE: '44', JM: '1876', JO: '962', JP: '81',
    KE: '254', KG: '996', KH: '855', KI: '686', KM: '269', KN: '1869', KP: '850', KR: '82', KW: '965', KY: '1345', KZ: '7',
    LA: '856', LB: '961', LC: '1758', LI: '423', LK: '94', LR: '231', LS: '266', LT: '370', LU: '352', LV: '371', LY: '218',
    MA: '212', MC: '377', MD: '373', ME: '382', MF: '590', MG: '261', MH: '692', MK: '389', ML: '223', MM: '95', MN: '976', MO: '853', MP: '1670', MQ: '596', MR: '222', MS: '1664', MT: '356', MU: '230', MV: '960', MW: '265', MX: '52', MY: '60', MZ: '258',
    NA: '264', NC: '687', NE: '227', NF: '672', NG: '234', NI: '505', NL: '31', NO: '47', NP: '977', NR: '674', NU: '683', NZ: '64',
    OM: '968',
    PA: '507', PE: '51', PF: '689', PG: '675', PH: '63', PK: '92', PL: '48', PM: '508', PN: '64', PR: '1787', PS: '970', PT: '351', PW: '680', PY: '595',
    QA: '974',
    RE: '262', RO: '40', RS: '381', RU: '7', RW: '250',
    SA: '966', SB: '677', SC: '248', SD: '249', SE: '46', SG: '65', SH: '290', SI: '386', SJ: '47', SK: '421', SL: '232', SM: '378', SN: '221', SO: '252', SR: '597', SS: '211', ST: '239', SV: '503', SX: '1721', SY: '963', SZ: '268',
    TC: '1649', TD: '235', TF: '262', TG: '228', TH: '66', TJ: '992', TK: '690', TL: '670', TM: '993', TN: '216', TO: '676', TR: '90', TT: '1868', TV: '688', TW: '886', TZ: '255',
    UA: '380', UG: '256', US: '1', UY: '598', UZ: '998',
    VA: '379', VC: '1784', VE: '58', VG: '1284', VI: '1340', VN: '84', VU: '678',
    WF: '681', WS: '685', XK: '383', YE: '967', YT: '262',
    ZA: '27', ZM: '260', ZW: '263',
};

const callingCodes = Object.keys(countryByCallingCode).sort((left, right) => right.length - left.length);
const allCallingCodes = [...new Set([...callingCodes, ...Object.values(callingCodeByCountry)])]
    .sort((left, right) => right.length - left.length);

const callingCodeFromPhoneNumber = (phoneNumber) => {
    const compact = String(phoneNumber).replace(/[().\-\s]/g, '');
    const internationalDigits = compact.startsWith('+')
        ? compact.slice(1)
        : compact.startsWith('00') ? compact.slice(2) : null;

    if (internationalDigits === null || !/^\d+$/.test(internationalDigits)) {
        return null;
    }

    return allCallingCodes.find((code) => internationalDigits.startsWith(code)) || null;
};

export const countryCodeFromPhoneNumber = (phoneNumber) => {
    const callingCode = callingCodeFromPhoneNumber(phoneNumber);

    return callingCode ? (countryByCallingCode[callingCode] || null) : null;
};

export const initializePhoneCountryDetection = () => {
    const phoneNumber = document.querySelector('[data-phone-number][data-phone-country-prefix]');
    const nationality = document.querySelector('[data-nationality-select]');

    if (!phoneNumber || !nationality) {
        return;
    }

    let nationalityWasManuallySelected = false;
    let activeCallingCode = callingCodeFromPhoneNumber(phoneNumber.value)
        || callingCodeByCountry[nationality.value]
        || null;

    const removeCallingCode = (value, callingCode) => {
        if (!callingCode) {
            return value.trim();
        }

        return value.trim().replace(new RegExp(`^(?:\\+|00)${callingCode}[\\s-]*`), '').trim();
    };

    const applyNationalityCallingCode = () => {
        const nextCallingCode = callingCodeByCountry[nationality.value];

        if (!nextCallingCode) {
            return;
        }

        const existingCallingCode = callingCodeFromPhoneNumber(phoneNumber.value) || activeCallingCode;
        const localNumber = removeCallingCode(phoneNumber.value, existingCallingCode);

        phoneNumber.value = localNumber === ''
            ? `+${nextCallingCode} `
            : `+${nextCallingCode} ${localNumber}`;
        activeCallingCode = nextCallingCode;
    };

    nationality.addEventListener('change', (event) => {
        if (event.isTrusted) {
            nationalityWasManuallySelected = true;
        }

        applyNationalityCallingCode();
    });

    applyNationalityCallingCode();

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
