<?php

namespace Hyperlab\Dimona\Services;

use Exception;
use Hyperlab\Dimona\Enums\Country;

class NisCodeService
{
    public static function new(): static
    {
        return app(static::class);
    }

    /*
     * Source: https://www.socialsecurity.be/portail/glossaires/dmfa.nsf/820da2a60329e7abc1256b210060ae67/b2ba2bfe49b73464c1256cde003328a9/$FILE/AN2003-2-Nl5.pdf
     */
    public function getNisCodeForCountry(Country $country): int
    {
        return match ($country) {
            Country::Belgium => 150,
            Country::Netherlands => 129,
        };
    }

    /*
     * Source: https://opendata.brussels.be/explore/dataset/codes-ins-nis-postaux-belgique/information
     */
    public function getNisCodeForMunicipality(string $postalCode): int
    {
        return match ($postalCode) {
            '1040' => 21004,
            '1050' => 21004,
            '1050' => 21009,
            '1060' => 21013,
            '1120' => 21004,
            '1130' => 21004,
            '1180' => 21016,
            '1210' => 21014,
            '1325' => 25018,
            '1330' => 25091,
            '1331' => 25091,
            '1000' => 21004,
            '1341' => 25121,
            '1020' => 21004,
            '1357' => 25118,
            '1080' => 21012,
            '1367' => 25122,
            '1200' => 21018,
            '1400' => 25072,
            '1301' => 25112,
            '1460' => 25044,
            '1340' => 25121,
            '1471' => 25031,
            '1390' => 25037,
            '1501' => 23027,
            '1450' => 25117,
            '1547' => 23009,
            '1461' => 25044,
            '1601' => 23077,
            '1490' => 25023,
            '1640' => 23101,
            '1602' => 23077,
            '1652' => 23003,
            '1673' => 23064,
            '1700' => 23016,
            '1742' => 23086,
            '1730' => 23002,
            '1853' => 23025,
            '1750' => 23104,
            '1880' => 23039,
            '1760' => 23097,
            '1910' => 23038,
            '1800' => 23088,
            '1932' => 23094,
            '1804' => 23088,
            '2000' => 11002,
            '1861' => 23050,
            '2018' => 11002,
            '2060' => 11002,
            '2020' => 11002,
            '2160' => 11052,
            '2030' => 11002,
            '2235' => 13016,
            '2050' => 11002,
            '2240' => 11054,
            '2180' => 11002,
            '2322' => 13014,
            '2222' => 12014,
            '2330' => 13023,
            '2243' => 11054,
            '2340' => 13004,
            '2320' => 13014,
            '2350' => 13046,
            '2323' => 13014,
            '2360' => 13031,
            '2440' => 13008,
            '2390' => 11057,
            '2460' => 13017,
            '2400' => 13025,
            '2490' => 13003,
            '2450' => 13021,
            '2491' => 13003,
            '2500' => 12021,
            '2560' => 12026,
            '2531' => 11004,
            '2610' => 11002,
            '2550' => 11024,
            '2650' => 11013,
            '2600' => 11002,
            '2845' => 11030,
            '2620' => 11018,
            '2850' => 11005,
            '2811' => 12025,
            '2870' => 12041,
            '2890' => 12041,
            '2910' => 11016,
            '2900' => 11040,
            '2920' => 11022,
            '2940' => 11044,
            '2930' => 11008,
            '3012' => 24062,
            '2950' => 11023,
            '3050' => 24086,
            '3018' => 24062,
            '3070' => 24055,
            '3071' => 24055,
            '3110' => 24094,
            '3090' => 23062,
            '3191' => 24014,
            '3111' => 24094,
            '3201' => 24001,
            '3210' => 24066,
            '3211' => 24066,
            '3270' => 24134,
            '3380' => 24137,
            '3271' => 24134,
            '3404' => 24059,
            '3290' => 24020,
            '3460' => 24008,
            '3300' => 24107,
            '3461' => 24008,
            '3321' => 24041,
            '3472' => 24054,
            '3350' => 24133,
            '3545' => 71020,
            '3370' => 24016,
            '3550' => 71070,
            '3470' => 24054,
            '3721' => 71072,
            '3473' => 24054,
            '3724' => 71072,
            '3510' => 71072,
            '3730' => 73110,
            '3582' => 71004,
            '3831' => 73098,
            '3660' => 72042,
            '3840' => 73111,
            '3720' => 71072,
            '3850' => 71045,
            '3740' => 73110,
            '3870' => 73022,
            '3770' => 73066,
            '3940' => 72038,
            '3792' => 73109,
            '3960' => 72004,
            '3798' => 73109,
            '4051' => 62022,
            '3806' => 71053,
            '4100' => 62096,
            '3950' => 72003,
            '4130' => 62032,
            '3970' => 71034,
            '4140' => 62100,
            '3980' => 71071,
            '4170' => 62026,
            '4032' => 62063,
            '4190' => 61019,
            '4171' => 62026,
            '4253' => 64029,
            '4218' => 61028,
            '4257' => 64008,
            '4317' => 64076,
            '4300' => 64074,
            '4452' => 62060,
            '4340' => 62006,
            '4458' => 62060,
            '4342' => 62006,
            '4500' => 61031,
            '4351' => 64063,
            '4520' => 61072,
            '4400' => 62120,
            '4530' => 61068,
            '4451' => 62060,
            '4550' => 61043,
            '4480' => 61080,
            '4590' => 61048,
            '4560' => 61012,
            '4621' => 62038,
            '4632' => 62099,
            '4653' => 63035,
            '4633' => 62099,
            '4690' => 62011,
            '4670' => 62119,
            '4710' => 63048,
            '4680' => 62079,
            '4728' => 63040,
            '4682' => 62079,
            '4771' => 63001,
            '4720' => 63040,
            '4783' => 63067,
            '4821' => 63020,
            '4784' => 63067,
            '4840' => 63084,
            '4834' => 63046,
            '4852' => 63088,
            '4845' => 63038,
            '4861' => 63058,
            '4850' => 63088,
            '4877' => 63057,
            '4851' => 63088,
            '4910' => 63076,
            '4900' => 63072,
            '4950' => 63080,
            '5000' => 92094,
            '4970' => 63073,
            '5021' => 92094,
            '4990' => 63045,
            '5031' => 92142,
            '5024' => 92094,
            '5330' => 92006,
            '5080' => 92141,
            '5360' => 91059,
            '5190' => 92140,
            '5363' => 91059,
            '5332' => 92006,
            '5501' => 91034,
            '5333' => 92006,
            '5524' => 91103,
            '5503' => 91034,
            '5580' => 91114,
            '5520' => 91103,
            '5640' => 92087,
            '5523' => 91103,
            '5644' => 92087,
            '5540' => 91142,
            '5660' => 93014,
            '5555' => 91015,
            '6110' => 52048,
            '5562' => 91072,
            '6182' => 52015,
            '5563' => 91072,
            '6183' => 52015,
            '5574' => 91013,
            '6210' => 52075,
            '5600' => 93056,
            '6221' => 52021,
            '5646' => 92087,
            '6223' => 52021,
            '5650' => 93088,
            '6238' => 52055,
            '5670' => 93090,
            '6280' => 52025,
            '6041' => 52011,
            '6463' => 56016,
            '6042' => 52011,
            '6530' => 56078,
            '6043' => 52011,
            '6533' => 56078,
            '6061' => 52011,
            '6536' => 56078,
            '6141' => 52022,
            '6600' => 82039,
            '6180' => 52015,
            '6660' => 82014,
            '6200' => 52012,
            '6666' => 82014,
            '6224' => 52021,
            '6674' => 82037,
            '6230' => 52055,
            '6680' => 82038,
            '6460' => 56016,
            '6687' => 82039,
            '6462' => 56016,
            '6706' => 81001,
            '6464' => 56016,
            '6717' => 81003,
            '6560' => 56022,
            '6743' => 85009,
            '6594' => 56051,
            '6750' => 85026,
            '6642' => 82036,
            '6762' => 85045,
            '6690' => 82032,
            '6782' => 81015,
            '6721' => 85046,
            '6791' => 81004,
            '6724' => 85046,
            '6792' => 81004,
            '6730' => 85039,
            '6821' => 85011,
            '6741' => 85009,
            '6824' => 85011,
            '6767' => 85047,
            '6838' => 84010,
            '6823' => 85011,
            '6840' => 84043,
            '6853' => 84050,
            '6880' => 84009,
            '6920' => 84075,
            '6900' => 83034,
            '6924' => 84075,
            '6922' => 84075,
            '6953' => 83040,
            '6952' => 83040,
            '6972' => 83049,
            '6984' => 83031,
            '6997' => 83013,
            '7012' => 53053,
            '7000' => 53053,
            '7020' => 53053,
            '7010' => 53053,
            '7024' => 53053,
            '7034' => 53053,
            '7032' => 53053,
            '7063' => 55040,
            '7041' => 53084,
            '7130' => 58002,
            '7090' => 55004,
            '7170' => 55086,
            '7120' => 58003,
            '7180' => 55085,
            '7131' => 58002,
            '7181' => 55085,
            '7190' => 55050,
            '7301' => 53014,
            '7350' => 53039,
            '7321' => 51009,
            '7500' => 57081,
            '7332' => 53070,
            '7504' => 57081,
            '7340' => 53082,
            '7521' => 57081,
            '7380' => 53068,
            '7531' => 57081,
            '7382' => 53068,
            '7532' => 57081,
            '7387' => 53083,
            '7536' => 57081,
            '7390' => 53065,
            '7538' => 57081,
            '7520' => 57081,
            '7540' => 57081,
            '7522' => 57081,
            '7542' => 57081,
            '7543' => 57081,
            '7610' => 57072,
            '7622' => 57093,
            '7621' => 57093,
            '7624' => 57093,
            '7643' => 57003,
            '7712' => 57096,
            '7780' => 57097,
            '7740' => 57062,
            '7781' => 57097,
            '7783' => 57097,
            '7812' => 51004,
            '7784' => 57097,
            '7850' => 51067,
            '7823' => 51004,
            '7880' => 51019,
            '7866' => 51069,
            '7901' => 57094,
            '7870' => 53046,
            '7943' => 51012,
            '7912' => 51065,
            '8211' => 31040,
            '7950' => 51014,
            '8301' => 31043,
            '8370' => 31004,
            '8420' => 35029,
            '8460' => 35014,
            '8430' => 35011,
            '8500' => 34022,
            '8431' => 35011,
            '8501' => 34022,
            '8434' => 35011,
            '8510' => 34022,
            '8470' => 35005,
            '8511' => 34022,
            '8480' => 35006,
            '8520' => 34023,
            '8540' => 34009,
            '8550' => 34042,
            '8553' => 34042,
            '8551' => 34042,
            '8572' => 34002,
            '8554' => 34042,
            '8583' => 34003,
            '8560' => 34041,
            '8630' => 38025,
            '8570' => 34002,
            '8670' => 38014,
            '8581' => 34003,
            '8700' => 37022,
            '8587' => 34043,
            '8720' => 37002,
            '8680' => 32010,
            '8770' => 36007,
            '8710' => 37017,
            '8820' => 31033,
            '8755' => 37021,
            '8850' => 37020,
            '8760' => 37022,
            '8851' => 37020,
            '8780' => 37010,
            '8870' => 36008,
            '8791' => 34040,
            '8930' => 34027,
            '8792' => 34040,
            '8952' => 33039,
            '8793' => 34040,
            '8954' => 33039,
            '8800' => 36015,
            '8958' => 33039,
            '8810' => 36011,
            '9051' => 44021,
            '8830' => 36006,
            '9060' => 43018,
            '8860' => 34025,
            '9070' => 44013,
            '8890' => 36012,
            '9090' => 44088,
            '8950' => 33039,
            '9185' => 44087,
            '8953' => 33039,
            '9551' => 41027,
            '9000' => 44021,
            '9620' => 41081,
            '9031' => 44021,
            '9661' => 45059,
            '9100' => 46021,
            '9870' => 44081,
            '9160' => 46029,
            '9880' => 44084,
            '9230' => 42025,
            '9881' => 44084,
            '9260' => 42026,
            '9960' => 43002,
            '9280' => 42011,
            '9990' => 43010,
            '9290' => 42003,
            '9308' => 41002,
            '9340' => 41034,
            '9472' => 41011,
            '9473' => 41011,
            '9660' => 45059,
            '9770' => 45068,
            '9820' => 44088,
            '9831' => 44064,
            '9920' => 44085,
            '9921' => 44085,
            '9932' => 44085,
            '9950' => 44085,
            '9988' => 43014,
            '9991' => 43010,
            '9992' => 43010,
            '1030' => 21015,
            '1040' => 21005,
            '1083' => 21008,
            '1090' => 21010,
            '1160' => 21002,
            '1190' => 21007,
            '1310' => 25050,
            '1350' => 25120,
            '1360' => 25084,
            '1370' => 25048,
            '1420' => 25014,
            '1421' => 25014,
            '1428' => 25014,
            '1473' => 25031,
            '1476' => 25031,
            '1480' => 25105,
            '1495' => 25107,
            '1500' => 23027,
            '1570' => 23106,
            '1654' => 23003,
            '1670' => 23064,
            '1731' => 23002,
            '1740' => 23086,
            '1741' => 23086,
            '1745' => 23060,
            '1755' => 23106,
            '1790' => 23105,
            '1831' => 23047,
            '1930' => 23094,
            '1950' => 23099,
            '1980' => 23096,
            '1981' => 23096,
            '2040' => 11002,
            '2170' => 11002,
            '2220' => 12014,
            '2223' => 12014,
            '2230' => 13013,
            '2242' => 11054,
            '2260' => 13049,
            '2270' => 13012,
            '2275' => 13019,
            '2300' => 13040,
            '2370' => 13001,
            '2380' => 13035,
            '2381' => 13035,
            '2382' => 13035,
            '2387' => 13002,
            '2430' => 13053,
            '2431' => 13053,
            '2480' => 13006,
            '2520' => 11035,
            '2540' => 11021,
            '2547' => 11025,
            '2627' => 11038,
            '2630' => 11001,
            '2830' => 12040,
            '2860' => 12035,
            '2980' => 11055,
            '2990' => 11053,
            '3000' => 24062,
            '3001' => 24062,
            '3010' => 24062,
            '3040' => 24045,
            '3052' => 24086,
            '3060' => 24009,
            '3061' => 24009,
            '3128' => 24109,
            '3190' => 24014,
            '3202' => 24001,
            '3221' => 24043,
            '3320' => 24041,
            '3360' => 24011,
            '3381' => 24137,
            '3384' => 24137,
            '3391' => 24135,
            '3401' => 24059,
            '3450' => 24028,
            '3500' => 71072,
            '3570' => 73001,
            '3580' => 71004,
            '3581' => 71004,
            '3600' => 71016,
            '3620' => 73042,
            '3723' => 71072,
            '3791' => 73109,
            '3891' => 71017,
            '3920' => 72020,
            '3930' => 72037,
            '3941' => 72038,
            '3971' => 71034,
            '3990' => 72030,
            '4000' => 62063,
            '4030' => 62063,
            '4040' => 62051,
            '4050' => 62022,
            '4053' => 62022,
            '4102' => 62096,
            '4120' => 62121,
            '4121' => 62121,
            '4122' => 62121,
            '4163' => 61079,
            '4210' => 61010,
            '4219' => 64075,
            '4263' => 64015,
            '4280' => 64034,
            '4367' => 64021,
            '4430' => 62003,
            '4431' => 62003,
            '4453' => 62060,
            '4460' => 62118,
            '4600' => 62108,
            '4602' => 62108,
            '4608' => 62027,
            '4623' => 62038,
            '4671' => 62119,
            '4672' => 62119,
            '4700' => 63023,
            '4711' => 63048,
            '4721' => 63040,
            '4761' => 63012,
            '4780' => 63067,
            '4801' => 63079,
            '4802' => 63079,
            '4837' => 63004,
            '4841' => 63084,
            '4890' => 63089,
            '4920' => 62009,
            '4960' => 63049,
            '4980' => 63086,
            '5001' => 92094,
            '5004' => 92094,
            '5022' => 92094,
            '5032' => 92142,
            '5060' => 92137,
            '5070' => 92048,
            '5300' => 92003,
            '5310' => 92035,
            '5353' => 92097,
            '5374' => 91064,
            '5377' => 91120,
            '5380' => 92138,
            '5500' => 91034,
            '5504' => 91034,
            '5521' => 91103,
            '5530' => 91141,
            '5537' => 91005,
            '5544' => 91142,
            '5550' => 91143,
            '5561' => 91072,
            '5570' => 91013,
            '5573' => 91013,
            '5590' => 91030,
            '5651' => 93088,
            '6000' => 52011,
            '6030' => 52011,
            '6044' => 52011,
            '6150' => 56001,
            '6181' => 52015,
            '6211' => 52075,
            '6250' => 52074,
            '6540' => 56044,
            '6593' => 56051,
            '6637' => 82009,
            '6681' => 82038,
            '6692' => 82032,
            '6704' => 81001,
            '6720' => 85046,
            '6723' => 85046,
            '6760' => 85045,
            '6780' => 81015,
            '6832' => 84010,
            '6833' => 84010,
            '6850' => 84050,
            '6852' => 84050,
            '6860' => 84033,
            '6927' => 84068,
            '6940' => 83012,
            '6941' => 83012,
            '6950' => 83040,
            '6971' => 83049,
            '6980' => 83031,
            '7021' => 53053,
            '7030' => 53053,
            '7031' => 53053,
            '7050' => 53044,
            '7062' => 55040,
            '7070' => 55035,
            '7100' => 58001,
            '7133' => 58002,
            '7134' => 58002,
            '7141' => 58004,
            '7160' => 52010,
            '7191' => 55050,
            '7320' => 51009,
            '7331' => 53070,
            '7334' => 53070,
            '7370' => 53020,
            '7501' => 57081,
            '7502' => 57081,
            '7503' => 57081,
            '7600' => 57064,
            '7601' => 57064,
            '7602' => 57064,
            '7603' => 57064,
            '7611' => 57072,
            '7618' => 57072,
            '7623' => 57093,
            '7641' => 57003,
            '7700' => 57096,
            '7730' => 57027,
            '7760' => 57018,
            '7800' => 51004,
            '7804' => 51004,
            '7810' => 51004,
            '7860' => 51069,
            '7861' => 51069,
            '7862' => 51069,
            '7910' => 51065,
            '7971' => 51008,
            '7973' => 51008,
            '8210' => 31040,
            '8377' => 31042,
            '8380' => 31005,
            '8421' => 35029,
            '8432' => 35011,
            '8450' => 35002,
            '8490' => 31012,
            '8582' => 34003,
            '8600' => 32003,
            '8647' => 32030,
            '8740' => 37011,
            '8750' => 37021,
            '8790' => 34040,
            '8880' => 36010,
            '8956' => 33039,
            '9040' => 44021,
            '9041' => 44021,
            '9042' => 44021,
            '9111' => 46021,
            '9120' => 46030,
            '9180' => 46029,
            '9220' => 42008,
            '9240' => 42028,
            '9270' => 42010,
            '9300' => 41002,
            '9400' => 41048,
            '9403' => 41048,
            '9450' => 41024,
            '9520' => 41063,
            '9521' => 41063,
            '9600' => 45041,
            '9630' => 45065,
            '9700' => 45035,
            '9750' => 45068,
            '9772' => 45068,
            '9810' => 44086,
            '9890' => 44020,
            '9900' => 43005,
            '9940' => 44019,
            '9970' => 43007,
            '9981' => 43014,
            '9982' => 43014,
            '1070' => 21001,
            '1081' => 21011,
            '1082' => 21003,
            '1140' => 21006,
            '1150' => 21019,
            '1300' => 25112,
            '1320' => 25005,
            '1332' => 25091,
            '1342' => 25121,
            '1401' => 25072,
            '1404' => 25072,
            '1410' => 25110,
            '1430' => 25123,
            '1440' => 25015,
            '1457' => 25124,
            '1502' => 23027,
            '1540' => 23106,
            '1541' => 23106,
            '1560' => 23033,
            '1600' => 23077,
            '1630' => 23100,
            '1653' => 23003,
            '1671' => 23064,
            '1701' => 23016,
            '1702' => 23016,
            '1703' => 23016,
            '1761' => 23097,
            '1770' => 23044,
            '1780' => 23102,
            '1804' => 23096,
            '1830' => 23047,
            '1850' => 23025,
            '1931' => 23047,
            '1982' => 23096,
            '2140' => 11002,
            '2150' => 11002,
            '2221' => 12014,
            '2288' => 13010,
            '2290' => 13044,
            '2328' => 13014,
            '2470' => 13036,
            '2530' => 11004,
            '2570' => 12009,
            '2580' => 12029,
            '2800' => 12025,
            '2820' => 12005,
            '2970' => 11039,
            '3053' => 24086,
            '3130' => 24007,
            '3140' => 24048,
            '3150' => 24033,
            '3220' => 24043,
            '3272' => 24134,
            '3293' => 24020,
            '3294' => 24020,
            '3390' => 24135,
            '3400' => 24059,
            '3440' => 24130,
            '3454' => 24028,
            '3512' => 71072,
            '3530' => 72039,
            '3621' => 73042,
            '3630' => 73107,
            '3631' => 73107,
            '3640' => 72018,
            '3650' => 72041,
            '3665' => 71002,
            '3668' => 71002,
            '3670' => 72042,
            '3680' => 72021,
            '3732' => 73110,
            '3742' => 73110,
            '3746' => 73110,
            '3790' => 73109,
            '3793' => 73109,
            '3830' => 73098,
            '3890' => 71017,
            '3900' => 72043,
            '3945' => 71071,
            '4041' => 62051,
            '4042' => 62051,
            '4160' => 61079,
            '4161' => 61079,
            '4181' => 61024,
            '4217' => 61028,
            '4250' => 64029,
            '4254' => 64029,
            '4260' => 64015,
            '4287' => 64047,
            '4347' => 64025,
            '4350' => 64063,
            '4357' => 64023,
            '4360' => 64056,
            '4432' => 62003,
            '4470' => 64065,
            '4537' => 61063,
            '4557' => 61081,
            '4570' => 61039,
            '4631' => 62099,
            '4650' => 63035,
            '4652' => 63035,
            '4681' => 62079,
            '4683' => 62079,
            '4730' => 63061,
            '4731' => 63061,
            '4750' => 63013,
            '4760' => 63012,
            '4770' => 63001,
            '4790' => 63087,
            '4800' => 63079,
            '4820' => 63020,
            '4860' => 63058,
            '4880' => 63003,
            '4987' => 63075,
            '5030' => 92142,
            '5100' => 92094,
            '5101' => 92094,
            '5140' => 92114,
            '5334' => 92006,
            '5340' => 92054,
            '5354' => 92097,
            '5361' => 91059,
            '5372' => 91064,
            '5522' => 91103,
            '5542' => 91142,
            '5543' => 91142,
            '5560' => 91072,
            '5571' => 91013,
            '5572' => 91013,
            '5575' => 91054,
            '5621' => 93022,
            '5630' => 93010,
            '5641' => 92087,
            '6010' => 52011,
            '6031' => 52011,
            '6032' => 52011,
            '6040' => 52011,
            '6060' => 52011,
            '6111' => 52048,
            '6120' => 56086,
            '6140' => 52022,
            '6220' => 52021,
            '6222' => 52021,
            '6440' => 56029,
            '6441' => 56029,
            '6470' => 56088,
            '6500' => 56005,
            '6531' => 56078,
            '6543' => 56044,
            '6591' => 56051,
            '6596' => 56051,
            '6640' => 82036,
            '6661' => 82014,
            '6662' => 82014,
            '6663' => 82014,
            '6671' => 82037,
            '6672' => 82037,
            '6673' => 82037,
            '6688' => 82039,
            '6700' => 81001,
            '6740' => 85009,
            '6742' => 85009,
            '6747' => 85034,
            '6761' => 85045,
            '6769' => 85024,
            '6781' => 81015,
            '6800' => 84077,
            '6820' => 85011,
            '6831' => 84010,
            '6834' => 84010,
            '6870' => 84059,
            '6890' => 84035,
            '6921' => 84075,
            '6960' => 83055,
            '6983' => 83031,
            '7011' => 53053,
            '7022' => 53053,
            '7040' => 53084,
            '7060' => 55040,
            '7061' => 55040,
            '7300' => 53014,
            '7322' => 51009,
            '7330' => 53070,
            '7506' => 57081,
            '7530' => 57081,
            '7533' => 57081,
            '7534' => 57081,
            '7640' => 57003,
            '7742' => 57062,
            '7750' => 57095,
            '7803' => 51004,
            '7811' => 51004,
            '7830' => 51068,
            '7864' => 51069,
            '7906' => 57094,
            '7940' => 51012,
            '7941' => 51012,
            '7942' => 51012,
            '7970' => 51008,
            '8340' => 31006,
            '8433' => 35011,
            '8531' => 34013,
            '8552' => 34042,
            '8573' => 34002,
            '8580' => 34003,
            '8620' => 38016,
            '8650' => 32006,
            '8690' => 38002,
            '8691' => 38002,
            '8840' => 36019,
            '8900' => 33011,
            '8904' => 33011,
            '8906' => 33011,
            '8908' => 33011,
            '8920' => 33040,
            '8940' => 33029,
            '8978' => 33021,
            '8980' => 33037,
            '9112' => 46021,
            '9150' => 46030,
            '9190' => 46024,
            '9200' => 42006,
            '9250' => 42023,
            '9310' => 41002,
            '9320' => 41002,
            '9401' => 41048,
            '9404' => 41048,
            '9406' => 41048,
            '9470' => 41011,
            '9550' => 41027,
            '9552' => 41027,
            '9571' => 45063,
            '9667' => 45062,
            '9688' => 45064,
            '9690' => 45060,
            '9771' => 45068,
            '9790' => 45061,
            '9800' => 44083,
            '9830' => 44064,
            '9850' => 44083,
            '9860' => 44052,
            '9961' => 43002,
            '9971' => 43007,
            '1170' => 21017,
            '1315' => 25043,
            '1348' => 25121,
            '1380' => 25119,
            '1402' => 25072,
            '1435' => 25068,
            '1470' => 25031,
            '1472' => 25031,
            '1474' => 25031,
            '1620' => 23098,
            '1650' => 23003,
            '1651' => 23003,
            '1674' => 23064,
            '1785' => 23052,
            '1820' => 23081,
            '1840' => 23045,
            '1851' => 23025,
            '1852' => 23025,
            '1860' => 23050,
            '1933' => 23094,
            '1970' => 23103,
            '2070' => 46030,
            '2100' => 11002,
            '2110' => 11050,
            '2200' => 13011,
            '2250' => 13029,
            '2280' => 13010,
            '2310' => 13037,
            '2321' => 13014,
            '2590' => 12002,
            '2640' => 11029,
            '2660' => 11002,
            '2801' => 12025,
            '2812' => 12025,
            '2840' => 11037,
            '2861' => 12035,
            '2880' => 12007,
            '2960' => 11009,
            '3020' => 24038,
            '3051' => 24086,
            '3054' => 24086,
            '3078' => 24055,
            '3080' => 24104,
            '3118' => 24094,
            '3120' => 24109,
            '3200' => 24001,
            '3212' => 24066,
            '3471' => 24054,
            '3501' => 71072,
            '3511' => 71072,
            '3520' => 71066,
            '3540' => 71024,
            '3560' => 71037,
            '3583' => 71004,
            '3590' => 71011,
            '3690' => 71067,
            '3700' => 73111,
            '3717' => 73028,
            '3722' => 71072,
            '3800' => 71053,
            '3803' => 71053,
            '3832' => 73098,
            '3910' => 72043,
            '4020' => 62063,
            '4031' => 62063,
            '4052' => 62022,
            '4101' => 62096,
            '4141' => 62100,
            '4162' => 61079,
            '4180' => 61024,
            '4252' => 64029,
            '4261' => 64015,
            '4420' => 62093,
            '4450' => 62060,
            '4540' => 61003,
            '4577' => 61041,
            '4601' => 62108,
            '4606' => 62027,
            '4607' => 62027,
            '4610' => 62015,
            '4620' => 62038,
            '4624' => 62038,
            '4630' => 62099,
            '4651' => 63035,
            '4654' => 63035,
            '4684' => 62079,
            '4701' => 63023,
            '4782' => 63067,
            '4791' => 63087,
            '4830' => 63046,
            '4831' => 63046,
            '4870' => 62122,
            '4983' => 63086,
            '5002' => 92094,
            '5003' => 92094,
            '5020' => 92094,
            '5081' => 92141,
            '5150' => 92045,
            '5170' => 92101,
            '5336' => 92006,
            '5350' => 92097,
            '5351' => 92097,
            '5352' => 92097,
            '5362' => 91059,
            '5364' => 91059,
            '5370' => 91064,
            '5376' => 91064,
            '5502' => 91034,
            '5541' => 91142,
            '5564' => 91072,
            '5576' => 91013,
            '5620' => 93022,
            '5680' => 93018,
            '6001' => 52011,
            '6020' => 52011,
            '6142' => 52022,
            '6240' => 52018,
            '6461' => 56016,
            '6511' => 56005,
            '6532' => 56078,
            '6534' => 56078,
            '6542' => 56044,
            '6567' => 56049,
            '6590' => 56051,
            '6592' => 56051,
            '6630' => 81013,
            '6670' => 82037,
            '6686' => 82039,
            '6698' => 82032,
            '6790' => 81004,
            '6810' => 85007,
            '6811' => 85007,
            '6812' => 85007,
            '6813' => 85007,
            '6830' => 84010,
            '6836' => 84010,
            '6851' => 84050,
            '6856' => 84050,
            '6887' => 84029,
            '6929' => 84016,
            '6951' => 83040,
            '6970' => 83049,
            '6982' => 83031,
            '6986' => 83031,
            '6987' => 83044,
            '6990' => 83028,
            '7033' => 53053,
            '7080' => 53028,
            '7110' => 58001,
            '7140' => 58004,
            '7333' => 53070,
            '7548' => 57081,
            '7604' => 57064,
            '7608' => 57064,
            '7620' => 57093,
            '7642' => 57003,
            '7711' => 57096,
            '7743' => 57062,
            '7782' => 57097,
            '7801' => 51004,
            '7802' => 51004,
            '7822' => 51004,
            '7863' => 51069,
            '7890' => 51017,
            '7900' => 57094,
            '7903' => 57094,
            '7904' => 57094,
            '7911' => 51065,
            '7951' => 51014,
            '7972' => 51008,
            '8000' => 31005,
            '8020' => 31022,
            '8200' => 31005,
            '8300' => 31043,
            '8310' => 31005,
            '8400' => 35013,
            '8530' => 34013,
            '8610' => 32011,
            '8640' => 33041,
            '8660' => 38008,
            '8730' => 31003,
            '8902' => 33011,
            '8951' => 33039,
            '8957' => 33016,
            '8970' => 33021,
            '8972' => 33021,
            '9030' => 44021,
            '9032' => 44021,
            '9050' => 44021,
            '9052' => 44021,
            '9080' => 44087,
            '9130' => 46030,
            '9140' => 46025,
            '9170' => 46020,
            '9255' => 42004,
            '9402' => 41048,
            '9420' => 41082,
            '9451' => 41024,
            '9500' => 41018,
            '9506' => 41018,
            '9570' => 45063,
            '9572' => 45063,
            '9636' => 45065,
            '9680' => 45064,
            '9681' => 45064,
            '9840' => 44086,
            '9910' => 44084,
            '9930' => 44085,
            '9931' => 44085,
            '9968' => 43002,
            '9980' => 43014,
            default => throw new Exception("Municipality not found for {$postalCode}."),
        };
    }
}
