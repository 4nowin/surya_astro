<?php

declare(strict_types=1);

namespace JmeEph\FFI;

use FFI;
use FFI\CData;
use FFI\Exception as FFIException;
use RuntimeException;

/**
 * @method string jme_version(CData $buffer, int $buffer_size)
 * @method float jme_julian_day(int $year, int $month, int $day, float $hour, int $calendar)
 * @method void jme_set_sidereal_mode(int $sidereal_mode, float $t0, float $ayan_t0)
 * @method void jme_set_ephemeris_path(string $path)
 * @method void jme_set_jpl_file(string $path)
 * @method void jme_set_astro_models(string $models, int $flags)
 * @method int jme_jpl_open(?string $path, CData $error)
 * @method ?CData jme_ephemeris_path()
 * @method ?CData jme_jpl_file()
 * @method int jme_calc_ut(float $jd_ut, int $body, int $flags, CData $results, CData $error)
 * @method int jme_houses(float $jd_ut, float $geo_lat, float $geo_lon, int $house_system, CData $cusps, CData $ascmc)
 * @method float jme_get_ayanamsa_ut(float $jd_ut)
 * @method int jme_lun_eclipse_when(float $jd_start, int $flags, int $iflag, CData $tret, int $backward, CData $error)
 * @method int jme_sol_eclipse_when_glob(float $jd_start, int $flags, int $epheflag, CData $tret, int $backward, CData $error)
 * @method int jme_lun_eclipse_how(float $jd_ut, int $flags, CData $geopos, CData $attr, CData $error)
 * @method int jme_lun_eclipse_when_loc(float $jd_start, int $flags, CData $geopos, CData $tret, CData $attr, int $backward, CData $error)
 * @method int jme_sol_eclipse_how(float $jd_ut, int $flags, CData $geopos, CData $attr, CData $error)
 * @method int jme_sol_eclipse_when_loc(float $jd_start, int $flags, CData $geopos, CData $tret, CData $attr, int $backward, CData $error)
 * @method void jme_jd_to_utc(float $jd, int $calendar, CData $year, CData $month, CData $day, CData $hour, CData $minute, CData $second)
 * @method int jme_rise_trans(float $jd_ut, int $body, ?string $starname, int $flags, int $rsmi, CData $geopos, float $atpress, float $attemp, CData $tret, CData $error)
 * @method int jme_time_scale_convert(float $jd1, float $jd2, int $from_scale, int $to_scale, CData $out1, CData $out2, CData $error)
 * @method ?CData jme_time_scale_name(int $scale)
 * @method int jme_leap_second_table_info(CData $jd_utc_valid_until, CData $last_tai_minus_utc, CData $error)
 * @method int jme_delta_t_iers_table_info(CData $jd_first, CData $jd_last_observed, CData $jd_last, CData $error)
 * @method int jme_get_ayanamsa_ex2(float $jd_et, int $model, int $flags, CData $ayan, CData $rate, CData $error)
 * @method int jme_get_ayanamsa_ex2_ut(float $jd_ut, int $model, int $flags, CData $ayan, CData $rate, CData $error)
 * @method int jme_set_topo_checked(float $lon, float $lat, float $altitude, CData $error)
 * @method int jme_set_sidereal_mode_checked(int $sidereal_mode, float $t0, float $ayan_t0, CData $error)
 * @method int jme_set_astro_models_checked(string $models, CData $error)
 * @method int jme_set_jpl_center_policy(int $policy, CData $error)
 * @method int jme_get_jpl_center_policy()
 * @method int jme_calc_resolve_engine(float $jd_et, int $body, CData $engine_id, CData $jpl_center_substituted, CData $error)
 * @method int jme_sidereal_time_ex(float $jd_ut, int $apparent, CData $hours, CData $error)
 * @method int jme_houses_gauquelin(float $jd_ut, int $flags, float $geo_lat, float $geo_lon, CData $cusps, CData $ascmc, CData $error)
 * @method int jme_houses_gauquelin_armc(float $armc, float $geo_lat, float $eps, CData $cusps, CData $ascmc, CData $error)
 * @method int jme_eop_load_file(string $path, CData $error)
 * @method void jme_eop_clear()
 * @method int jme_eop_coverage(CData $mjd_first, CData $mjd_last, CData $error)
 * @method int jme_eop_polar_motion(float $mjd, CData $xp_arcsec, CData $yp_arcsec, CData $predicted, CData $error)
 * @method void jme_set_polar_motion(int $enabled)
 * @method int jme_get_polar_motion()
 */
class JmeEphFFI
{
    public const JME_JME_H = 1;
    public const JME_VERSION = '2.0.0';
    public const JME_AU_KM = 149597870.7;
    public const JME_SPEED_OF_LIGHT_KM_PER_SEC = 299792.458;
    public const JME_SECONDS_PER_DAY = 86400.0;
    public const JME_OK = 0;
    public const JME_ERR = -1;
    public const JME_CALENDAR_JULIAN = 0;
    public const JME_CALENDAR_GREGORIAN = 1;
    public const JME_BODY_SUN = 0;
    public const JME_BODY_MOON = 1;
    public const JME_BODY_MERCURY = 2;
    public const JME_BODY_VENUS = 3;
    public const JME_BODY_MARS = 4;
    public const JME_BODY_JUPITER = 5;
    public const JME_BODY_SATURN = 6;
    public const JME_BODY_URANUS = 7;
    public const JME_BODY_NEPTUNE = 8;
    public const JME_BODY_PLUTO = 9;
    public const JME_BODY_EARTH = 10;
    public const JME_BODY_SOLAR_SYSTEM_BARYCENTER = 11;
    public const JME_BODY_MERCURY_BARYCENTER = 12;
    public const JME_BODY_VENUS_BARYCENTER = 13;
    public const JME_BODY_EARTH_MOON_BARYCENTER = 14;
    public const JME_BODY_MARS_BARYCENTER = 15;
    public const JME_BODY_JUPITER_BARYCENTER = 16;
    public const JME_BODY_SATURN_BARYCENTER = 17;
    public const JME_BODY_URANUS_BARYCENTER = 18;
    public const JME_BODY_NEPTUNE_BARYCENTER = 19;
    public const JME_BODY_PLUTO_BARYCENTER = 20;
    public const JME_BODY_MEAN_NODE = 21;
    public const JME_BODY_TRUE_NODE = 22;
    public const JME_CALC_NONE = 0;
    public const JME_CALC_SPEED = 1;
    public const JME_CALC_EQUATORIAL = 2;
    public const JME_CALC_XYZ = 4;
    public const JME_CALC_RADIANS = 8;
    public const JME_CALC_BARYCENTRIC = 16;
    public const JME_CALC_HELIOCENTRIC = 32;
    public const JME_CALC_TRUE_POSITION = 64;
    public const JME_CALC_J2000 = 128;
    public const JME_CALC_NO_NUTATION = 256;
    public const JME_CALC_SIDEREAL = 512;
    public const JME_SIDEREAL_FAGAN_BRADLEY = 0;
    public const JME_SIDEREAL_LAHIRI = 1;
    public const JME_SIDEREAL_USER = 255;
    public const JME_VECTOR_AU_PER_DAY = 0;
    public const JME_VECTOR_KM_PER_DAY = 1;
    public const JME_VECTOR_AU_PER_SECOND = 2;
    public const JME_VECTOR_KM_PER_SECOND = 3;
    public const JME_ORIENTATION_RAD_PER_DAY = 0;
    public const JME_ORIENTATION_RAD_PER_SECOND = 1;
    public const JME_JPL_TIMESCALE_UNKNOWN = 0;
    public const JME_JPL_TIMESCALE_TDB = 1;
    public const JME_JPL_TIMESCALE_TCB = 2;
    public const JME_EXTENDED_H = 1;
    public const JME_ANGLE_FORMAT_KEEP_DEG = 0;
    public const JME_ANGLE_FORMAT_KEEP_SIGN = 1;
    public const JME_ANGLE_FORMAT_NAKSHATRA = 2;
    public const JME_ANGLE_FORMAT_ROUND_DEG = 3;
    public const JME_ANGLE_FORMAT_ROUND_MIN = 4;
    public const JME_ANGLE_FORMAT_ROUND_SEC = 5;
    public const JME_ANGLE_FORMAT_ZODIACAL = 6;
    public const JME_BODY_ALTJIRA = -1000;
    public const JME_BODY_AMYCUS = -1001;
    public const JME_BODY_ARROKOTH = -1002;
    public const JME_BODY_ASBOLUS = -1003;
    public const JME_BODY_ASTEROID_001 = -1004;
    public const JME_BODY_ASTEROID_002 = -1005;
    public const JME_BODY_ASTEROID_003 = -1006;
    public const JME_BODY_ASTEROID_004 = -1007;
    public const JME_BODY_ASTEROID_005 = -1008;
    public const JME_BODY_ASTEROID_006 = -1009;
    public const JME_BODY_ASTEROID_007 = -1010;
    public const JME_BODY_ASTEROID_008 = -1011;
    public const JME_BODY_ASTEROID_009 = -1012;
    public const JME_BODY_ASTEROID_010 = -1013;
    public const JME_BODY_ASTEROID_011 = -1014;
    public const JME_BODY_ASTEROID_012 = -1015;
    public const JME_BODY_ASTEROID_013 = -1016;
    public const JME_BODY_ASTEROID_014 = -1017;
    public const JME_BODY_ASTEROID_015 = -1018;
    public const JME_BODY_ASTEROID_016 = -1019;
    public const JME_BODY_ASTEROID_017 = -1020;
    public const JME_BODY_ASTEROID_018 = -1021;
    public const JME_BODY_ASTEROID_019 = -1022;
    public const JME_BODY_ASTEROID_020 = -1023;
    public const JME_BODY_ASTEROID_021 = -1024;
    public const JME_BODY_ASTEROID_022 = -1025;
    public const JME_BODY_ASTEROID_023 = -1026;
    public const JME_BODY_ASTEROID_024 = -1027;
    public const JME_BODY_ASTEROID_025 = -1028;
    public const JME_BODY_ASTEROID_026 = -1029;
    public const JME_BODY_ASTEROID_027 = -1030;
    public const JME_BODY_ASTEROID_028 = -1031;
    public const JME_BODY_ASTEROID_029 = -1032;
    public const JME_BODY_ASTEROID_030 = -1033;
    public const JME_BODY_ASTEROID_031 = -1034;
    public const JME_BODY_ASTEROID_032 = -1035;
    public const JME_BODY_ASTEROID_033 = -1036;
    public const JME_BODY_ASTEROID_034 = -1037;
    public const JME_BODY_ASTEROID_035 = -1038;
    public const JME_BODY_ASTEROID_036 = -1039;
    public const JME_BODY_ASTEROID_037 = -1040;
    public const JME_BODY_ASTEROID_038 = -1041;
    public const JME_BODY_ASTEROID_039 = -1042;
    public const JME_BODY_ASTEROID_040 = -1043;
    public const JME_BODY_ASTEROID_041 = -1044;
    public const JME_BODY_ASTEROID_042 = -1045;
    public const JME_BODY_ASTEROID_043 = -1046;
    public const JME_BODY_ASTEROID_044 = -1047;
    public const JME_BODY_ASTEROID_045 = -1048;
    public const JME_BODY_ASTEROID_046 = -1049;
    public const JME_BODY_ASTEROID_047 = -1050;
    public const JME_BODY_ASTEROID_048 = -1051;
    public const JME_BODY_ASTEROID_049 = -1052;
    public const JME_BODY_ASTEROID_050 = -1053;
    public const JME_BODY_ASTEROID_051 = -1054;
    public const JME_BODY_ASTEROID_052 = -1055;
    public const JME_BODY_ASTEROID_053 = -1056;
    public const JME_BODY_ASTEROID_054 = -1057;
    public const JME_BODY_ASTEROID_055 = -1058;
    public const JME_BODY_ASTEROID_056 = -1059;
    public const JME_BODY_ASTEROID_057 = -1060;
    public const JME_BODY_ASTEROID_058 = -1061;
    public const JME_BODY_ASTEROID_059 = -1062;
    public const JME_BODY_ASTEROID_060 = -1063;
    public const JME_BODY_ASTEROID_061 = -1064;
    public const JME_BODY_ASTEROID_062 = -1065;
    public const JME_BODY_ASTEROID_063 = -1066;
    public const JME_BODY_ASTEROID_064 = -1067;
    public const JME_BODY_ASTEROID_065 = -1068;
    public const JME_BODY_ASTEROID_066 = -1069;
    public const JME_BODY_ASTEROID_067 = -1070;
    public const JME_BODY_ASTEROID_068 = -1071;
    public const JME_BODY_ASTEROID_069 = -1072;
    public const JME_BODY_ASTEROID_070 = -1073;
    public const JME_BODY_ASTEROID_071 = -1074;
    public const JME_BODY_ASTEROID_072 = -1075;
    public const JME_BODY_ASTEROID_073 = -1076;
    public const JME_BODY_ASTEROID_074 = -1077;
    public const JME_BODY_ASTEROID_075 = -1078;
    public const JME_BODY_ASTEROID_076 = -1079;
    public const JME_BODY_ASTEROID_077 = -1080;
    public const JME_BODY_ASTEROID_078 = -1081;
    public const JME_BODY_ASTEROID_079 = -1082;
    public const JME_BODY_ASTEROID_080 = -1083;
    public const JME_BODY_ASTEROID_081 = -1084;
    public const JME_BODY_ASTEROID_082 = -1085;
    public const JME_BODY_ASTEROID_083 = -1086;
    public const JME_BODY_ASTEROID_084 = -1087;
    public const JME_BODY_ASTEROID_085 = -1088;
    public const JME_BODY_ASTEROID_086 = -1089;
    public const JME_BODY_ASTEROID_087 = -1090;
    public const JME_BODY_ASTEROID_088 = -1091;
    public const JME_BODY_ASTEROID_089 = -1092;
    public const JME_BODY_ASTEROID_090 = -1093;
    public const JME_BODY_ASTEROID_091 = -1094;
    public const JME_BODY_ASTEROID_092 = -1095;
    public const JME_BODY_ASTEROID_093 = -1096;
    public const JME_BODY_ASTEROID_094 = -1097;
    public const JME_BODY_ASTEROID_095 = -1098;
    public const JME_BODY_ASTEROID_096 = -1099;
    public const JME_BODY_ASTEROID_097 = -1100;
    public const JME_BODY_ASTEROID_098 = -1101;
    public const JME_BODY_ASTEROID_099 = -1102;
    public const JME_BODY_ASTEROID_100 = -1103;
    public const JME_BODY_ASTEROID_101 = -1104;
    public const JME_BODY_ASTEROID_102 = -1105;
    public const JME_BODY_ASTEROID_103 = -1106;
    public const JME_BODY_ASTEROID_104 = -1107;
    public const JME_BODY_ASTEROID_105 = -1108;
    public const JME_BODY_ASTEROID_106 = -1109;
    public const JME_BODY_ASTEROID_107 = -1110;
    public const JME_BODY_ASTEROID_108 = -1111;
    public const JME_BODY_ASTEROID_109 = -1112;
    public const JME_BODY_ASTEROID_110 = -1113;
    public const JME_BODY_BIENOR = -1114;
    public const JME_BODY_BORASISI = -1115;
    public const JME_BODY_CERES = -1116;
    public const JME_BODY_CHARIKLO = -1117;
    public const JME_BODY_CHIRON = -1118;
    public const JME_BODY_COMET_001 = -1119;
    public const JME_BODY_COMET_002 = -1120;
    public const JME_BODY_COMET_003 = -1121;
    public const JME_BODY_COMET_004 = -1122;
    public const JME_BODY_COMET_005 = -1123;
    public const JME_BODY_COMET_006 = -1124;
    public const JME_BODY_COMET_007 = -1125;
    public const JME_BODY_COMET_008 = -1126;
    public const JME_BODY_COMET_009 = -1127;
    public const JME_BODY_COMET_010 = -1128;
    public const JME_BODY_COMET_011 = -1129;
    public const JME_BODY_COMET_012 = -1130;
    public const JME_BODY_COMET_013 = -1131;
    public const JME_BODY_COMET_014 = -1132;
    public const JME_BODY_COMET_015 = -1133;
    public const JME_BODY_COMET_016 = -1134;
    public const JME_BODY_COMET_017 = -1135;
    public const JME_BODY_COMET_018 = -1136;
    public const JME_BODY_COMET_019 = -1137;
    public const JME_BODY_COMET_020 = -1138;
    public const JME_BODY_COMET_021 = -1139;
    public const JME_BODY_COMET_022 = -1140;
    public const JME_BODY_COMET_023 = -1141;
    public const JME_BODY_COMET_024 = -1142;
    public const JME_BODY_COMET_025 = -1143;
    public const JME_BODY_COMET_026 = -1144;
    public const JME_BODY_COMET_027 = -1145;
    public const JME_BODY_COMET_028 = -1146;
    public const JME_BODY_COMET_029 = -1147;
    public const JME_BODY_COMET_030 = -1148;
    public const JME_BODY_COMET_031 = -1149;
    public const JME_BODY_COMET_032 = -1150;
    public const JME_BODY_COMET_033 = -1151;
    public const JME_BODY_COMET_034 = -1152;
    public const JME_BODY_COMET_035 = -1153;
    public const JME_BODY_COMET_036 = -1154;
    public const JME_BODY_COMET_037 = -1155;
    public const JME_BODY_COMET_038 = -1156;
    public const JME_BODY_COMET_039 = -1157;
    public const JME_BODY_COMET_040 = -1158;
    public const JME_BODY_COMET_041 = -1159;
    public const JME_BODY_COMET_042 = -1160;
    public const JME_BODY_COMET_043 = -1161;
    public const JME_BODY_COMET_044 = -1162;
    public const JME_BODY_COMET_045 = -1163;
    public const JME_BODY_COMET_046 = -1164;
    public const JME_BODY_COMET_047 = -1165;
    public const JME_BODY_COMET_048 = -1166;
    public const JME_BODY_COMET_049 = -1167;
    public const JME_BODY_COMET_050 = -1168;
    public const JME_BODY_COMET_051 = -1169;
    public const JME_BODY_COMET_052 = -1170;
    public const JME_BODY_COMET_053 = -1171;
    public const JME_BODY_COMET_054 = -1172;
    public const JME_BODY_COMET_055 = -1173;
    public const JME_BODY_COMET_056 = -1174;
    public const JME_BODY_COMET_057 = -1175;
    public const JME_BODY_COMET_058 = -1176;
    public const JME_BODY_COMET_059 = -1177;
    public const JME_BODY_COMET_060 = -1178;
    public const JME_BODY_CRANTOR = -1179;
    public const JME_BODY_CYLLARUS = -1180;
    public const JME_BODY_DEUCALION = -1181;
    public const JME_BODY_ECHECLUS = -1182;
    public const JME_BODY_ELATUS = -1183;
    public const JME_BODY_ERIS = -1184;
    public const JME_BODY_GONGGONG = -1185;
    public const JME_BODY_HAUMEA = -1186;
    public const JME_BODY_HUYA = -1187;
    public const JME_BODY_HYLONOME = -1188;
    public const JME_BODY_IXION = -1189;
    public const JME_BODY_JUNO = -1190;
    public const JME_BODY_MAKEMAKE = -1191;
    public const JME_BODY_MINOR_PLANET_001 = -1192;
    public const JME_BODY_MINOR_PLANET_002 = -1193;
    public const JME_BODY_MINOR_PLANET_003 = -1194;
    public const JME_BODY_MINOR_PLANET_004 = -1195;
    public const JME_BODY_MINOR_PLANET_005 = -1196;
    public const JME_BODY_MINOR_PLANET_006 = -1197;
    public const JME_BODY_MINOR_PLANET_007 = -1198;
    public const JME_BODY_MINOR_PLANET_008 = -1199;
    public const JME_BODY_MINOR_PLANET_009 = -1200;
    public const JME_BODY_MINOR_PLANET_010 = -1201;
    public const JME_BODY_MINOR_PLANET_011 = -1202;
    public const JME_BODY_MINOR_PLANET_012 = -1203;
    public const JME_BODY_MINOR_PLANET_013 = -1204;
    public const JME_BODY_MINOR_PLANET_014 = -1205;
    public const JME_BODY_MINOR_PLANET_015 = -1206;
    public const JME_BODY_MINOR_PLANET_016 = -1207;
    public const JME_BODY_MINOR_PLANET_017 = -1208;
    public const JME_BODY_MINOR_PLANET_018 = -1209;
    public const JME_BODY_MINOR_PLANET_019 = -1210;
    public const JME_BODY_MINOR_PLANET_020 = -1211;
    public const JME_BODY_MINOR_PLANET_021 = -1212;
    public const JME_BODY_MINOR_PLANET_022 = -1213;
    public const JME_BODY_MINOR_PLANET_023 = -1214;
    public const JME_BODY_MINOR_PLANET_024 = -1215;
    public const JME_BODY_MINOR_PLANET_025 = -1216;
    public const JME_BODY_MINOR_PLANET_026 = -1217;
    public const JME_BODY_MINOR_PLANET_027 = -1218;
    public const JME_BODY_MINOR_PLANET_028 = -1219;
    public const JME_BODY_MINOR_PLANET_029 = -1220;
    public const JME_BODY_MINOR_PLANET_030 = -1221;
    public const JME_BODY_MINOR_PLANET_031 = -1222;
    public const JME_BODY_MINOR_PLANET_032 = -1223;
    public const JME_BODY_MINOR_PLANET_033 = -1224;
    public const JME_BODY_MINOR_PLANET_034 = -1225;
    public const JME_BODY_MINOR_PLANET_035 = -1226;
    public const JME_BODY_MINOR_PLANET_036 = -1227;
    public const JME_BODY_MINOR_PLANET_037 = -1228;
    public const JME_BODY_MINOR_PLANET_038 = -1229;
    public const JME_BODY_MINOR_PLANET_039 = -1230;
    public const JME_BODY_MINOR_PLANET_040 = -1231;
    public const JME_BODY_MINOR_PLANET_041 = -1232;
    public const JME_BODY_MINOR_PLANET_042 = -1233;
    public const JME_BODY_MINOR_PLANET_043 = -1234;
    public const JME_BODY_MINOR_PLANET_044 = -1235;
    public const JME_BODY_MINOR_PLANET_045 = -1236;
    public const JME_BODY_MINOR_PLANET_046 = -1237;
    public const JME_BODY_MINOR_PLANET_047 = -1238;
    public const JME_BODY_MINOR_PLANET_048 = -1239;
    public const JME_BODY_MINOR_PLANET_049 = -1240;
    public const JME_BODY_MINOR_PLANET_050 = -1241;
    public const JME_BODY_MINOR_PLANET_051 = -1242;
    public const JME_BODY_MINOR_PLANET_052 = -1243;
    public const JME_BODY_MINOR_PLANET_053 = -1244;
    public const JME_BODY_MINOR_PLANET_054 = -1245;
    public const JME_BODY_MINOR_PLANET_055 = -1246;
    public const JME_BODY_MINOR_PLANET_056 = -1247;
    public const JME_BODY_MINOR_PLANET_057 = -1248;
    public const JME_BODY_MINOR_PLANET_058 = -1249;
    public const JME_BODY_MINOR_PLANET_059 = -1250;
    public const JME_BODY_MINOR_PLANET_060 = -1251;
    public const JME_BODY_NESSUS = -1252;
    public const JME_BODY_OKYRHOE = -1253;
    public const JME_BODY_ORCUS = -1254;
    public const JME_BODY_PALLAS = -1255;
    public const JME_BODY_PELOPS = -1256;
    public const JME_BODY_PHOLUS = -1257;
    public const JME_BODY_QUAOAR = -1258;
    public const JME_BODY_RHADAMANTHUS = -1259;
    public const JME_BODY_SALACIA = -1260;
    public const JME_BODY_SEDNA = -1261;
    public const JME_BODY_THEREUS = -1262;
    public const JME_BODY_TYTHONUS = -1263;
    public const JME_BODY_VARUNA = -1264;
    public const JME_BODY_VESTA = -1265;
    public const JME_CALC_APPARENT_POSITION = 0;
    public const JME_CALC_ASTROMETRIC = 3072;
    public const JME_CALC_CENTER_BODY = 4096;
    public const JME_CALC_DISTANCE_AU = 0;
    public const JME_CALC_DISTANCE_KM = 8192;
    public const JME_CALC_HIGH_PRECISION = 16384;
    public const JME_CALC_ICRS = 32768;
    public const JME_CALC_NO_ABERRATION = 1024;
    public const JME_CALC_NO_LIGHT_DEFLECTION = 2048;
    public const JME_CALC_RAW_VECTOR = 4;
    public const JME_CALC_RECTANGULAR = 4;
    public const JME_CALC_SPHERICAL = 0;
    public const JME_CALC_STRICT = 65536;
    public const JME_CALC_TOPOCENTRIC = 131072;
    public const JME_CALC_VELOCITY_PER_DAY = 0;
    public const JME_CALC_VELOCITY_PER_SECOND = 262144;
    public const JME_COORD_APPARENT_TO_TRUE = 289;
    public const JME_COORD_ECLIPTIC_TO_HORIZONTAL = 290;
    public const JME_COORD_EQUATORIAL_TO_HORIZONTAL = 291;
    public const JME_COORD_HORIZONTAL_TO_ECLIPTIC = 292;
    public const JME_COORD_HORIZONTAL_TO_EQUATORIAL = 293;
    public const JME_COORD_TRUE_TO_APPARENT = 294;
    public const JME_ECLIPSE_FIRST_CONTACT = 295;
    public const JME_ECLIPSE_FOURTH_CONTACT = 296;
    public const JME_ECLIPSE_LUNAR_PARTIAL = 297;
    public const JME_ECLIPSE_LUNAR_PENUMBRAL = 298;
    public const JME_ECLIPSE_LUNAR_TOTAL = 299;
    public const JME_ECLIPSE_MAX_VISIBLE = 300;
    public const JME_ECLIPSE_PENUMBRAL_BEGIN = 301;
    public const JME_ECLIPSE_PENUMBRAL_END = 302;
    public const JME_ECLIPSE_SECOND_CONTACT = 303;
    public const JME_ECLIPSE_SOLAR_ANNULAR = 304;
    public const JME_ECLIPSE_SOLAR_CENTRAL = 305;
    public const JME_ECLIPSE_SOLAR_HYBRID = 306;
    public const JME_ECLIPSE_SOLAR_NONCENTRAL = 307;
    public const JME_ECLIPSE_SOLAR_PARTIAL = 308;
    public const JME_ECLIPSE_SOLAR_TOTAL = 309;
    public const JME_ECLIPSE_THIRD_CONTACT = 310;
    public const JME_ECLIPSE_VISIBLE = 311;
    public const JME_HOUSE_ALCABITIUS = 312;
    public const JME_HOUSE_APC = 313;
    public const JME_HOUSE_AZIMUTHAL = 314;
    public const JME_HOUSE_CAMPANUS = 315;
    public const JME_HOUSE_EQUAL = 316;
    public const JME_HOUSE_GAUQUELIN = 317;
    public const JME_HOUSE_HORIZONTAL = 318;
    public const JME_HOUSE_KOCH = 319;
    public const JME_HOUSE_KRUSINSKI = 320;
    public const JME_HOUSE_MERIDIAN = 321;
    public const JME_HOUSE_MORINUS = 322;
    public const JME_HOUSE_PLACIDUS = 323;
    public const JME_HOUSE_POLICH_PAGE = 324;
    public const JME_HOUSE_PORPHYRIUS = 325;
    public const JME_HOUSE_REGIOMONTANUS = 326;
    public const JME_HOUSE_SUNSHINE = 327;
    public const JME_HOUSE_VEHLOW_EQUAL = 328;
    public const JME_HOUSE_WHOLE_SIGN = 329;
    public const JME_MODEL_BIAS_IAU2000 = 330;
    public const JME_MODEL_BIAS_IAU2006 = 331;
    public const JME_MODEL_BIAS_NONE = 332;
    public const JME_MODEL_NUT_IAU_1980 = 333;
    public const JME_MODEL_NUT_IAU_2000A = 334;
    public const JME_MODEL_NUT_IAU_2000B = 335;
    public const JME_MODEL_OBL_IAU_1980 = 336;
    public const JME_MODEL_OBL_IAU_2000 = 337;
    public const JME_MODEL_OBL_IAU_2006 = 338;
    public const JME_MODEL_PREC_IAU_1976 = 339;
    public const JME_MODEL_PREC_IAU_2000 = 340;
    public const JME_MODEL_PREC_IAU_2006 = 341;
    public const JME_MODEL_PREC_LASKAR_1986 = 342;
    public const JME_MODEL_PREC_VONDRAK_2011 = 343;
    public const JME_MODEL_SIDT_IAU_1976 = 344;
    public const JME_MODEL_SIDT_IAU_2006 = 345;
    public const JME_MODEL_DELTAT_STEPHENSON_MORRISON_1984 = 346;
    public const JME_MODEL_DELTAT_STEPHENSON_1997 = 347;
    public const JME_MODEL_DELTAT_STEPHENSON_MORRISON_2004 = 348;
    public const JME_MODEL_DELTAT_ESPENAK_MEEUS_2006 = 349;
    public const JME_MODEL_DELTAT_STEPHENSON_ETC_2016 = 350;
    public const JME_MODEL_DELTAT_STEPHENSON_ETC_2021 = 407;
    public const JME_MODEL_DELTAT_MS2004_TABLE1_LINEAR = 408;
    public const JME_MODEL_NUT_IAU_2006A = 351;
    public const JME_RISE_ANTI_MERIDIAN_TRANSIT = 8;
    public const JME_RISE_ASTRONOMICAL_TWILIGHT = 512;
    public const JME_RISE_CIVIL_TWILIGHT = 128;
    public const JME_RISE_DISC_BOTTOM = 2048;
    public const JME_RISE_DISC_CENTER = 256;
    public const JME_RISE_FIXED_DISC_SIZE = 1024;
    public const JME_RISE_HINDU_RISING = 4096;
    public const JME_RISE_MERIDIAN_TRANSIT = 4;
    public const JME_RISE_NAUTICAL_TWILIGHT = 64;
    public const JME_RISE_NO_REFRACTION = 8192;
    public const JME_RISE_RISE = 1;
    public const JME_RISE_SET = 2;
    public const JME_SIDEREAL_ALDEBARAN_15TAU = 363;
    public const JME_SIDEREAL_ARYABHATA = 364;
    public const JME_SIDEREAL_B1950 = 365;
    public const JME_SIDEREAL_BABYL_ETPSC = 366;
    public const JME_SIDEREAL_BABYL_HUBER = 367;
    public const JME_SIDEREAL_BABYL_KUGLER1 = 368;
    public const JME_SIDEREAL_BABYL_KUGLER2 = 369;
    public const JME_SIDEREAL_BABYL_KUGLER3 = 370;
    public const JME_SIDEREAL_DELUCE = 371;
    public const JME_SIDEREAL_GALCENT_0SAG = 372;
    public const JME_SIDEREAL_HIPPARCHOS = 373;
    public const JME_SIDEREAL_J1900 = 374;
    public const JME_SIDEREAL_J2000 = 375;
    public const JME_SIDEREAL_JN_BHASIN = 376;
    public const JME_SIDEREAL_KRISHNAMURTI = 377;
    public const JME_SIDEREAL_RAMAN = 378;
    public const JME_SIDEREAL_SASSANIAN = 379;
    public const JME_SIDEREAL_SS_CITRA = 380;
    public const JME_SIDEREAL_SS_REVATI = 381;
    public const JME_SIDEREAL_SURYASIDDHANTA = 382;
    public const JME_SIDEREAL_TRUE_CITRA = 383;
    public const JME_SIDEREAL_TRUE_MULA = 384;
    public const JME_SIDEREAL_TRUE_PUSHYA = 385;
    public const JME_SIDEREAL_TRUE_REVATI = 386;
    public const JME_SIDEREAL_USHASHASHI = 387;
    public const JME_SIDEREAL_YUKTESHWAR = 388;
    public const JME_TIME_DELTAT_AUTOMATIC = 389;
    public const JME_TIME_TIDAL_AUTOMATIC = 390;
    public const JME_TIME_TIDAL_DE200 = 391;
    public const JME_TIME_TIDAL_DE403 = 392;
    public const JME_TIME_TIDAL_DE404 = 393;
    public const JME_TIME_TIDAL_DE405 = 394;
    public const JME_TIME_TIDAL_DE406 = 395;
    public const JME_TIME_TIDAL_DE421 = 396;
    public const JME_TIME_TIDAL_DE430 = 397;
    public const JME_TIME_TIDAL_DE431 = 398;
    public const JME_TIME_TIDAL_DE441 = 399;
    public const JME_VERSION_ID = 400;
    public const JME_MODEL_REVISED_IAU_2000 = 401;
    public const JME_MODEL_REVISED_IAU_2006 = 402;
    public const JME_MODEL_REVISED_PREC_LASKAR = 403;
    public const JME_MODEL_REVISED_PREC_VONDRAK = 404;
    public const JME_MODEL_REVISED_PREC_LIESKE = 405;
    public const JME_SIDEREAL_LAHIRI_ICRC = 406;
    public const JME_TIME_SCALE_UTC = 0;
    public const JME_TIME_SCALE_UT1 = 1;
    public const JME_TIME_SCALE_TAI = 2;
    public const JME_TIME_SCALE_TT = 3;
    public const JME_TIME_SCALE_TDB = 4;
    public const JME_TIME_SCALE_TCG = 5;
    public const JME_TIME_SCALE_TCB = 6;
    public const JME_AYANAMSA_MEAN = 0;
    public const JME_AYANAMSA_TRUE = 1;
    public const JME_JPL_CENTER_STRICT = 0;
    public const JME_JPL_CENTER_BARYCENTER = 1;
    public const JME_ENGINE_ID_JPL = 1;
    public const JME_ENGINE_ID_MOSHIER = 2;
    public const JME_ENGINE_ID_VSOP87_ELP82B = 3;

    private const array OPTIONAL_API_FUNCTIONS = [
        'jme_calc_resolve_engine',
        'jme_delta_t_iers_table_info',
        'jme_get_ayanamsa_ex2',
        'jme_get_ayanamsa_ex2_ut',
        'jme_get_jpl_center_policy',
        'jme_houses_gauquelin',
        'jme_houses_gauquelin_armc',
        'jme_eop_load_file',
        'jme_eop_clear',
        'jme_eop_coverage',
        'jme_eop_polar_motion',
        'jme_set_polar_motion',
        'jme_get_polar_motion',
        'jme_leap_second_table_info',
        'jme_set_astro_models_checked',
        'jme_set_jpl_center_policy',
        'jme_set_sidereal_mode_checked',
        'jme_set_topo_checked',
        'jme_sidereal_time_ex',
        'jme_time_scale_convert',
        'jme_time_scale_name',
    ];
    /** @var array<string, array{calls: int, ns: int}> */
    private static array $profile = [];

    /** @var array<string, array<string, true>> */
    private static array $profileUniqueInputs = [];

    private static ?bool $profileEnabled = null;

    private static bool $profileWritten = false;

    private readonly FFI $ffi;
    private bool $extendedApiAvailable = false;
    private ?string $extendedApiLoadError = null;

    public function __construct(?string $libraryPath = null)
    {
        $cdef = <<<'CDEF'
            int jme_body_id_from_name(const char *name);
            int jme_body_naif_id(int body);
            const char *jme_body_name(int body);
            int jme_calc(double jd_et, int body, int flags, double *results, char *error);
            int jme_calc_pctr(double jd_et, int body, int center, int flags, double *results, char *error);
            int jme_calc_resolve_engine(double jd_et, int body, int *engine_id, int *jpl_center_substituted, char *error);
            int jme_calc_ut(double jd_ut, int body, int flags, double *results, char *error);
            int jme_calendar_is_leap_year(int year, int calendar);
            int jme_centiseconds_difference(int p1, int p2);
            int jme_centiseconds_difference_signed(int p1, int p2);
            int jme_centiseconds_normalize(int p);
            int jme_centiseconds_round_second(int x);
            char *jme_centiseconds_to_degree_string(int cs, char *buffer);
            char *jme_centiseconds_to_lonlat_string(int cs, char *buffer);
            char *jme_centiseconds_to_time_string(int cs, char *buffer);
            void jme_close(void);
            char *jme_copy_body_name(int body, char *buffer);
            int jme_date_is_valid(int year, int month, int day, int calendar);
            int jme_day_of_week(double jd);
            int jme_day_of_year(int year, int month, int day, int calendar);
            int jme_days_in_month(int year, int month, int calendar);
            double jme_decimal_hour(int hour, int minute, double second);
            double jme_degree_midpoint(double x1, double x0);
            double jme_degree_normalize(double x);
            double jme_degrees_difference(double p1, double p2);
            double jme_degrees_difference_signed(double p1, double p2);
            double jme_degrees_to_hours(double degrees);
            double jme_degrees_to_radians(double degrees);
            double jme_delta_t(double jd_ut);
            double jme_delta_t_ex(double jd_ut, int model, char *error);
            int jme_delta_t_iers_table_info(double *jd_first, double *jd_last_observed, double *jd_last, char *error);
            int jme_double_to_long(double x);
            void jme_ecliptic_to_equatorial( double lon, double lat, double eps, double *ra, double *dec );
            int jme_ecliptic_to_equatorial_rectangular_state( const double *ecliptic, double eps, double *equatorial );
            int jme_elp2000_moon_state(double jd_et, double *results);
            void jme_eop_clear(void);
            int jme_eop_coverage(double *mjd_first, double *mjd_last, char *error);
            int jme_eop_load_file(const char *path, char *error);
            int jme_eop_polar_motion(double mjd, double *xp_arcsec, double *yp_arcsec, int *predicted, char *error);
            const char *jme_ephemeris_path(void);
            void jme_equatorial_to_ecliptic( double ra, double dec, double eps, double *lon, double *lat );
            int jme_equatorial_to_ecliptic_rectangular_state( const double *equatorial, double eps, double *ecliptic );
            void jme_equatorial_to_horizontal( double hour_angle, double dec, double geo_lat, double *azimuth, double *altitude );
            int jme_fixstar(const char *star, double jd_et, int flags, double *results, char *error);
            int jme_fixstar2(const char *star, double jd_et, int flags, double *results, char *error);
            int jme_fixstar2_mag(const char *star, double *mag, char *error);
            int jme_fixstar2_ut(const char *star, double jd_ut, int flags, double *results, char *error);
            int jme_fixstar_mag(const char *star, double *mag, char *error);
            int jme_fixstar_ut(const char *star, double jd_ut, int flags, double *results, char *error);
            int jme_gauquelin_sector(double jd_ut, int body, const char *starname, int flags, int imeth, double *geopos, double atpress, double attemp, double *dgsect, char *error);
            int jme_get_astro_models(char *models, int flags);
            double jme_get_ayanamsa(double jd_et);
            int jme_get_ayanamsa_ex(double jd_et, int model, double *ayan, char *error);
            int jme_get_ayanamsa_ex2(double jd_et, int model, int flags, double *ayan, double *rate, char *error);
            int jme_get_ayanamsa_ex2_ut(double jd_ut, int model, int flags, double *ayan, double *rate, char *error);
            int jme_get_ayanamsa_ex_ut(double jd_ut, int model, double *ayan, char *error);
            const char *jme_get_ayanamsa_name(int model);
            double jme_get_ayanamsa_ut(double jd_ut);
            int jme_get_frame_bias_matrix(int model, double *m);
            int jme_get_jpl_center_policy(void);
            int jme_get_nutation(double jd_et, int model, double *dpsi, double *deps, char *error);
            void jme_get_nutation_matrix(double dpsi_rad, double deps_rad, double eps_rad, double *m);
            int jme_get_obliquity(double jd_et, int model, double *eps, char *error);
            int jme_get_orbital_elements(double jd_et, int body, int flags, double *elem, char *error);
            int jme_get_polar_motion(void);
            int jme_get_precession_matrix(double jd_start, double jd_end, int model, double *m);
            void jme_get_sidereal_mode(int *sidereal_mode, double *t0, double *ayan_t0);
            double jme_get_tid_acc(void);
            int jme_get_topo_pos(double jd_et, double *pos_au, char *error);
            double jme_heliacal_angle(double jd_ut, double *geopos, double *dat_hel, char *error);
            int jme_heliacal_pheno_ut(double jd_ut, double *geopos, double *dat_hel, char *error);
            int jme_heliacal_ut(double jd_ut, double *geopos, double *dat_hel, char *error);
            int jme_helio_cross(int body, double x2cross, double jd_ut, int flags, double *tret, char *error);
            int jme_helio_cross_ut(int body, double x2cross, double jd_ut, int flags, double *tret, char *error);
            void jme_horizontal_to_equatorial( double azimuth, double altitude, double geo_lat, double *hour_angle, double *dec );
            double jme_hours_normalize(double hours);
            double jme_hours_to_degrees(double hours);
            double jme_house_pos(double armc, double geo_lat, double eps, int house_system, double *xpin, char *error);
            const char *jme_house_system_name(int house_system);
            int jme_houses(double jd_ut, double geo_lat, double geo_lon, int house_system, double *cusps, double *ascmc);
            int jme_houses_armc(double armc, double geo_lat, double eps, int house_system, double *cusps, double *ascmc);
            int jme_houses_armc_ex2(double armc, double geo_lat, double eps, int house_system, double *cusps, double *ascmc, double *cusps_speed, double *ascmc_speed);
            int jme_houses_ex(double jd_ut, int flags, double geo_lat, double geo_lon, int house_system, double *cusps, double *ascmc);
            int jme_houses_ex2(double jd_ut, int flags, double geo_lat, double geo_lon, int house_system, double *cusps, double *ascmc, double *cusps_speed, double *ascmc_speed);
            int jme_houses_gauquelin(double jd_ut, int flags, double geo_lat, double geo_lon, double *cusps, double *ascmc, char *error);
            int jme_houses_gauquelin_armc(double armc, double geo_lat, double eps, double *cusps, double *ascmc, char *error);
            double jme_jd_add_seconds(double jd, double seconds);
            double jme_jd_difference_seconds(double jd_end, double jd_start);
            void jme_jd_to_utc( double jd, int calendar, int *year, int *month, int *day, int *hour, int *minute, double *second );
            int jme_jpl_body_state( double jd_time, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_body_state_naif( double jd_time, int target_naif, int center_naif, int unit, double *state, char *error );
            int jme_jpl_body_state_native( double jd_time, int target_body, int center_body, double *state, char *error );
            int jme_jpl_body_state_native_naif( double jd_time, int target_naif, int center_naif, double *state, char *error );
            int jme_jpl_body_state_native_split( double jd0, double time_offset, int target_body, int center_body, double *state, char *error );
            int jme_jpl_body_state_native_split_naif( double jd0, double time_offset, int target_naif, int center_naif, double *state, char *error );
            int jme_jpl_body_state_order( double jd0, double time_offset, int target_body, int center_body, int unit, int order, double *state, char *error );
            int jme_jpl_body_state_order_naif( double jd0, double time_offset, int target_naif, int center_naif, int unit, int order, double *state, char *error );
            int jme_jpl_body_state_split( double jd0, double time_offset, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_body_state_split_naif( double jd0, double time_offset, int target_naif, int center_naif, int unit, double *state, char *error );
            int jme_jpl_body_state_utc( int year, int month, int day, int hour, int minute, double second, int calendar, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_body_state_utc_naif( int year, int month, int day, int hour, int minute, double second, int calendar, int target_naif, int center_naif, int unit, double *state, char *error );
            void jme_jpl_close(void);
            int jme_jpl_constant(const char *name, double *value, char *error);
            int jme_jpl_constant_count(char *error);
            int jme_jpl_constant_index(int index, char *name, unsigned int name_size, double *value, char *error);
            int jme_jpl_constant_string(const char *name, char *value, unsigned int value_size, char *error);
            int jme_jpl_constant_string_vector( const char *name, char *values, unsigned int single_value_size, int value_count, char *error );
            int jme_jpl_constant_vector(const char *name, double *values, int value_count, char *error);
            int jme_jpl_coverage(double *first_time, double *last_time, int *continuous, char *error);
            int jme_jpl_current_file_data( char *path, unsigned int path_size, double *first_time, double *last_time, int *continuous, char *error );
            int jme_jpl_ecliptic_state( double jd_time, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_ecliptic_state_naif( double jd_time, int target_naif, int center_naif, int unit, double *state, char *error );
            int jme_jpl_ecliptic_state_split( double jd0, double time_offset, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_ecliptic_state_split_naif( double jd0, double time_offset, int target_naif, int center_naif, int unit, double *state, char *error );
            int jme_jpl_ecliptic_state_utc( int year, int month, int day, int hour, int minute, double second, int calendar, int target_body, int center_body, int unit, double *state, char *error );
            int jme_jpl_ecliptic_state_utc_naif( int year, int month, int day, int hour, int minute, double second, int calendar, int target_naif, int center_naif, int unit, double *state, char *error );
            const char *jme_jpl_engine_version(char *buffer, size_t buffer_size);
            const char *jme_jpl_file(void);
            int jme_jpl_file_version(char *buffer, unsigned int buffer_size, char *error);
            int jme_jpl_id_by_name(const char *name, int *id, char *error);
            int jme_jpl_is_available(void);
            int jme_jpl_is_open(void);
            int jme_jpl_is_thread_safe(char *error);
            int jme_jpl_max_supported_order(int segment_type);
            int jme_jpl_name_by_id(int id, char *name, unsigned int name_size, char *error);
            int jme_jpl_open(const char *path, char *error);
            int jme_jpl_open_array(int path_count, const char *const *paths, char *error);
            int jme_jpl_orientation_record_count(char *error);
            int jme_jpl_orientation_record_index( int index, int *target, double *first_time, double *last_time, int *frame, int *segment_type, char *error );
            int jme_jpl_orientation_state_naif( double jd_time, int target_naif, int unit, double *state, char *error );
            int jme_jpl_orientation_state_order_naif( double jd0, double time_offset, int target_naif, int unit, int order, double *state, char *error );
            int jme_jpl_orientation_state_split_naif( double jd0, double time_offset, int target_naif, int unit, double *state, char *error );
            int jme_jpl_orientation_state_utc_naif( int year, int month, int day, int hour, int minute, double second, int calendar, int target_naif, int unit, double *state, char *error );
            int jme_jpl_position_record_count(char *error);
            int jme_jpl_position_record_index( int index, int *target, int *center, double *first_time, double *last_time, int *frame, int *segment_type, char *error );
            int jme_jpl_prefetch(char *error);
            int jme_jpl_rotational_angular_momentum_state_naif( double jd_time, int target_naif, int unit, double *state, char *error );
            int jme_jpl_rotational_angular_momentum_state_order_naif( double jd0, double time_offset, int target_naif, int unit, int order, double *state, char *error );
            int jme_jpl_rotational_angular_momentum_state_split_naif( double jd0, double time_offset, int target_naif, int unit, double *state, char *error );
            int jme_jpl_rotational_angular_momentum_state_utc_naif( int year, int month, int day, int hour, int minute, double second, int calendar, int target_naif, int unit, double *state, char *error );
            int jme_jpl_timescale(void);
            double jme_julian_day(int year, int month, int day, double hour, int calendar);
            int jme_lat_to_lmt(double jd_lat, double geo_lon, double *jd_lmt, char *error);
            int jme_leap_second_table_info(double *jd_utc_valid_until, double *last_tai_minus_utc, char *error);
            const char *jme_library_path(void);
            int jme_lmt_to_lat(double jd_lmt, double geo_lon, double *jd_lat, char *error);
            int jme_lun_eclipse_how(double jd_ut, int flags, double *geopos, double *attr, char *error);
            int jme_lun_eclipse_when(double jd_start, int flags, int iflag, double *tret, int backward, char *error);
            int jme_lun_eclipse_when_loc(double jd_start, int flags, double *geopos, double *tret, double *attr, int backward, char *error);
            int jme_lun_occult_when_glob(double jd_start, int body, const char *starname, int flags, int iflag, double *tret, int backward, char *error);
            int jme_lun_occult_when_loc(double jd_start, int body, const char *starname, int flags, double *geopos, double *tret, double *attr, int backward, char *error);
            int jme_lun_occult_where(double jd_ut, int body, const char *starname, int flags, double *geopos, double *attr, char *error);
            void jme_matrix_identity(double *m);
            void jme_matrix_multiply(const double *a, const double *b, double *out);
            void jme_matrix_rotate_x(double angle_rad, double *m);
            void jme_matrix_rotate_y(double angle_rad, double *m);
            void jme_matrix_rotate_z(double angle_rad, double *m);
            void jme_matrix_transform_state(const double *m, const double *input, double *output);
            int jme_meeus_moon_state(double jd_et, double *results);
            int jme_meeus_planet_state(double jd_et, int body, double *results);
            int jme_meeus_sun_state(double jd_et, double *results);
            int jme_mooncross(double x2cross, double jd_ut, int flags, double *tret, char *error);
            int jme_mooncross_node(double jd_ut, int flags, double *tret, char *error);
            int jme_mooncross_node_ut(double jd_ut, int flags, double *tret, char *error);
            int jme_mooncross_ut(double x2cross, double jd_ut, int flags, double *tret, char *error);
            int jme_moshier_planet_state(double jd_et, int body, double *results);
            int jme_nod_aps(double jd_et, int body, int flags, int method, double *tret, char *error);
            int jme_nod_aps_ut(double jd_ut, int body, int flags, int method, double *tret, char *error);
            int jme_orbit_max_min_true_distance(double jd_et, int body, int flags, double *tmax, double *tmin, double *dmax, double *dmin, char *error);
            int jme_pheno(double jd_et, int body, int flags, double *attr, char *error);
            int jme_pheno_ut(double jd_ut, int body, int flags, double *attr, char *error);
            double jme_radian_midpoint(double x1, double x0);
            double jme_radian_normalize(double x);
            double jme_radians_difference_signed(double p1, double p2);
            double jme_radians_to_degrees(double radians);
            int jme_rectangular_to_spherical_state(const double *rectangular, double *spherical);
            double jme_refract(double altitude, double pressure, double temperature, int calc_flag);
            double jme_refract_extended( double altitude, double geoalt, double pressure, double temperature, double lapse_rate, int calc_flag, double *out );
            void jme_reverse_julian_day( double jd, int calendar, int *year, int *month, int *day, double *hour );
            int jme_rise_trans(double jd_ut, int body, const char *starname, int flags, int rsmi, double *geopos, double atpress, double attemp, double *tret, char *error);
            int jme_rise_trans_true_hor(double jd_ut, int body, const char *starname, int flags, int rsmi, double *geopos, double atpress, double attemp, double horhgt, double *tret, char *error);
            void jme_set_astro_models(const char *models, int flags);
            int jme_set_astro_models_checked(const char *models, char *error);
            void jme_set_delta_t_userdef(double dt);
            void jme_set_ephemeris_path(const char *path);
            void jme_set_interpolate_nut(int on);
            int jme_set_jpl_center_policy(int policy, char *error);
            void jme_set_jpl_file(const char *path);
            void jme_set_lapse_rate(double lapse_rate);
            void jme_set_polar_motion(int enabled);
            void jme_set_sidereal_mode(int sidereal_mode, double t0, double ayan_t0);
            int jme_set_sidereal_mode_checked(int sidereal_mode, double t0, double ayan_t0, char *error);
            void jme_set_tid_acc(double t_acc);
            void jme_set_topo(double lon, double lat, double altitude);
            int jme_set_topo_checked(double lon, double lat, double altitude, char *error);
            double jme_sidereal_time(double jd_ut);
            double jme_sidereal_time0(double jd_ut, double eps, double nut);
            int jme_sidereal_time_ex(double jd_ut, int apparent, double *hours, char *error);
            int jme_sol_eclipse_how(double jd_ut, int flags, double *geopos, double *attr, char *error);
            int jme_sol_eclipse_when_glob(double jd_start, int flags, int epheflag, double *tret, int backward, char *error);
            int jme_sol_eclipse_when_loc(double jd_start, int flags, double *geopos, double *tret, double *attr, int backward, char *error);
            int jme_sol_eclipse_where(double jd_ut, int flags, double *geopos, double *attr, char *error);
            int jme_solcross(double x2cross, double jd_ut, int flags, double *tret, char *error);
            int jme_solcross_ut(double x2cross, double jd_ut, int flags, double *tret, char *error);
            double jme_spherical_angular_separation(double lon1, double lat1, double lon2, double lat2);
            double jme_spherical_position_angle(double lon1, double lat1, double lon2, double lat2);
            int jme_spherical_to_rectangular_state(const double *spherical, double *rectangular);
            void jme_split_degree( double ddeg, int roundflag, int *ideg, int *imin, int *isec, double *dsecfr, int *isgn );
            int jme_state_add(const double *left, const double *right, double *output);
            int jme_state_convert_units(const double *input, int input_unit, int output_unit, double *output);
            double jme_state_distance(const double *state);
            double jme_state_light_time_days(const double *state, int unit);
            double jme_state_position_velocity_dot(const double *state);
            int jme_state_scale(const double *input, double factor, double *output);
            double jme_state_speed(const double *state);
            int jme_state_subtract(const double *left, const double *right, double *output);
            int jme_time_equ(double jd_ut, double *e, char *error);
            int jme_time_scale_convert(double jd1, double jd2, int from_scale, int to_scale, double *out1, double *out2, char *error);
            const char *jme_time_scale_name(int scale);
            double jme_topo_arcus_visionis(double jd_ut, double *geopos, double *dat_hel, char *error);
            void jme_utc_time_zone( int year, int month, int day, int hour, int minute, double second, double timezone, int *out_year, int *out_month, int *out_day, int *out_hour, int *out_minute, double *out_second );
            int jme_utc_to_jd( int year, int month, int day, int hour, int minute, double second, int calendar, double *jd_utc );
            const char *jme_version(char *buffer, size_t buffer_size);
            int jme_vis_limit_mag(double jd_ut, double *geopos, double *dat_hel, char *error);
            int jme_vsop87_planet_state(double jd_et, int body, double *results);
CDEF;

        if ($libraryPath === null) {
            $libraryPath = $this->defaultLibraryPath();
        }

        if (! file_exists($libraryPath)) {
            throw new RuntimeException('JME shared library not found at: ' . $libraryPath);
        }

        $this->prepareLibraryDirectory($libraryPath);
        $optionalFunctionPattern = '/\b(?:' . implode('|', array_map(
            static fn(string $function): string => preg_quote($function, '/'),
            self::OPTIONAL_API_FUNCTIONS
        )) . ')\s*\(/';
        $baseCdef = implode("\n", array_filter(
            explode("\n", $cdef),
            static fn(string $line): bool => preg_match($optionalFunctionPattern, $line) !== 1
        ));

        try {
            $this->ffi = FFI::cdef($cdef, $libraryPath);
            $this->extendedApiAvailable = true;
        } catch (FFIException $exception) {
            if (! $this->isExtendedApiResolutionFailure($exception->getMessage())) {
                throw $exception;
            }

            // Released runtimes may predate the additive extended API. Fall
            // back to the core declarations in a single FFI scope so CData
            // pointers remain type-compatible for every core call.
            $this->ffi = FFI::cdef($baseCdef, $libraryPath);
            $this->extendedApiLoadError = $exception->getMessage();
        }
    }

    public function __call(string $name, array $arguments)
    {
        if (in_array($name, self::OPTIONAL_API_FUNCTIONS, true) && ! $this->extendedApiAvailable) {
            throw new RuntimeException(sprintf(
                'Native JME library does not expose extended API %s. Use a runtime built from a core revision that includes the extended API. Loader detail: %s',
                $name,
                $this->extendedApiLoadError ?? 'optional symbols are unavailable'
            ));
        }

        if (! $this->isProfileEnabled()) {
            return $this->ffi->$name(...$arguments);
        }

        $start = hrtime(true);
        try {
            return $this->ffi->$name(...$arguments);
        } finally {
            $this->recordProfile($name, hrtime(true) - $start);
        }
    }

    public static function writeProfileReport(): void
    {
        if (self::$profileWritten) {
            return;
        }
        self::$profileWritten = true;

        if (self::$profile === []) {
            return;
        }

        uasort(self::$profile, static fn(array $a, array $b): int => $b['ns'] <=> $a['ns']);
        fwrite(STDERR, "[jme-ffi-profile] method,calls,total_s,per_call_s\n");
        foreach (self::$profile as $name => $row) {
            $calls = max(1, $row['calls']);
            $total = $row['ns'] / 1_000_000_000;
            fwrite(
                STDERR,
                sprintf("[jme-ffi-profile] %s,%d,%.9f,%.12f\n", $name, $row['calls'], $total, $total / $calls)
            );
            if (isset(self::$profileUniqueInputs[$name])) {
                fwrite(STDERR, sprintf("[jme-ffi-profile-unique] %s,%d\n", $name, count(self::$profileUniqueInputs[$name])));
            }
        }
    }

    public function supportsExtendedApi(): bool
    {
        return $this->extendedApiAvailable;
    }

    public function jme_julian_day(int $year, int $month, int $day, float $hour, int $calendar): float
    {
        return $this->ffi->jme_julian_day($year, $month, $day, $hour, $calendar);
    }

    public function jme_set_sidereal_mode(int $sidereal_mode, float $t0, float $ayan_t0): void
    {
        $this->ffi->jme_set_sidereal_mode($sidereal_mode, $t0, $ayan_t0);
    }

    public function jme_set_ephemeris_path(string $path): void
    {
        $this->profileVoidCall('jme_set_ephemeris_path', function () use ($path): void {
            $this->ffi->jme_set_ephemeris_path($path);
        });
    }

    public function jme_set_jpl_file(string $path): void
    {
        $this->profileVoidCall('jme_set_jpl_file', function () use ($path): void {
            $this->ffi->jme_set_jpl_file($path);
        });
    }

    public function jme_set_astro_models(string $models, int $flags): void
    {
        $this->profileVoidCall('jme_set_astro_models', function () use ($flags, $models): void {
            $this->ffi->jme_set_astro_models($models, $flags);
        });
    }

    public function configureEngine(string $engine, ?string $ephemerisPath = null, ?string $jplFile = null): void
    {
        $engine = $this->normalizeEngine($engine);

        if (is_string($ephemerisPath) && $ephemerisPath !== '') {
            $this->jme_set_ephemeris_path($ephemerisPath);
        }

        if (is_string($jplFile) && $jplFile !== '') {
            $this->jme_set_jpl_file($jplFile);
        }

        if ($engine === 'JPL') {
            $kernelPath = $this->resolveUsableJplKernelPath($ephemerisPath, $jplFile);
            $error = $this->ffi->new('char[256]');

            $this->jme_set_jpl_file($kernelPath);
            $openResult = $this->jme_jpl_open($kernelPath, $error);
            if ($openResult !== self::JME_OK) {
                throw new RuntimeException('ENGINE=JPL is configured but unusable: ' . FFI::string($error));
            }
        }

        $this->jme_set_astro_models('ENGINE=' . $engine, 0);
    }

    public function jme_jpl_open(?string $path, CData $error): int
    {
        return $this->profileCall('jme_jpl_open', fn(): int => $this->ffi->jme_jpl_open($path, $error));
    }

    public function jme_calc_ut(float $jd_ut, int $body, int $flags, CData $results, CData $error): int
    {
        $name = 'jme_calc_ut/body=' . $body . '/flags=' . $flags;
        if ($this->isProfileEnabled()) {
            self::$profileUniqueInputs[$name][$body . '|' . $flags . '|' . sprintf('%.17g', $jd_ut)] = true;
        }

        return $this->profileCall($name, fn(): int => $this->ffi->jme_calc_ut($jd_ut, $body, $flags, $results, $error));
    }

    public function jme_houses(float $jd_ut, float $geo_lat, float $geo_lon, int $house_system, CData $cusps, CData $ascmc): int
    {
        return $this->profileCall('jme_houses', fn(): int => $this->ffi->jme_houses($jd_ut, $geo_lat, $geo_lon, $house_system, $cusps, $ascmc));
    }

    public function jme_get_ayanamsa_ut(float $jd_ut): float
    {
        return $this->profileCall('jme_get_ayanamsa_ut', fn(): float => $this->ffi->jme_get_ayanamsa_ut($jd_ut));
    }

    public function jme_lun_eclipse_when(float $jd_start, int $flags, int $iflag, CData $tret, int $backward, CData $error): int
    {
        return $this->profileCall('jme_lun_eclipse_when', fn(): int => $this->ffi->jme_lun_eclipse_when($jd_start, $flags, $iflag, $tret, $backward, $error));
    }

    public function jme_sol_eclipse_when_glob(float $jd_start, int $flags, int $epheflag, CData $tret, int $backward, CData $error): int
    {
        return $this->profileCall('jme_sol_eclipse_when_glob', fn(): int => $this->ffi->jme_sol_eclipse_when_glob($jd_start, $flags, $epheflag, $tret, $backward, $error));
    }

    public function jme_lun_eclipse_how(float $jd_ut, int $flags, CData $geopos, CData $attr, CData $error): int
    {
        return $this->profileCall('jme_lun_eclipse_how', fn(): int => $this->ffi->jme_lun_eclipse_how($jd_ut, $flags, $geopos, $attr, $error));
    }

    public function jme_lun_eclipse_when_loc(float $jd_start, int $flags, CData $geopos, CData $tret, CData $attr, int $backward, CData $error): int
    {
        return $this->profileCall('jme_lun_eclipse_when_loc', fn(): int => $this->ffi->jme_lun_eclipse_when_loc($jd_start, $flags, $geopos, $tret, $attr, $backward, $error));
    }

    public function jme_sol_eclipse_how(float $jd_ut, int $flags, CData $geopos, CData $attr, CData $error): int
    {
        return $this->profileCall('jme_sol_eclipse_how', fn(): int => $this->ffi->jme_sol_eclipse_how($jd_ut, $flags, $geopos, $attr, $error));
    }

    public function jme_sol_eclipse_when_loc(float $jd_start, int $flags, CData $geopos, CData $tret, CData $attr, int $backward, CData $error): int
    {
        return $this->profileCall('jme_sol_eclipse_when_loc', fn(): int => $this->ffi->jme_sol_eclipse_when_loc($jd_start, $flags, $geopos, $tret, $attr, $backward, $error));
    }

    public function jme_jd_to_utc(float $jd, int $calendar, CData $year, CData $month, CData $day, CData $hour, CData $minute, CData $second): void
    {
        $this->profileVoidCall('jme_jd_to_utc', function () use ($calendar, $day, $hour, $jd, $minute, $month, $second, $year): void {
            $this->ffi->jme_jd_to_utc($jd, $calendar, $year, $month, $day, $hour, $minute, $second);
        });
    }

    public function jme_rise_trans(float $jd_ut, int $body, ?string $starname, int $flags, int $rsmi, CData $geopos, float $atpress, float $attemp, CData $tret, CData $error): int
    {
        return $this->profileCall('jme_rise_trans', fn(): int => $this->ffi->jme_rise_trans($jd_ut, $body, $starname, $flags, $rsmi, $geopos, $atpress, $attemp, $tret, $error));
    }

    public function getFFI(): FFI
    {
        return $this->ffi;
    }

    public function jme_ephemeris_path(): string
    {
        $path = $this->ffi->jme_ephemeris_path();

        if ($path === null) {
            return '';
        }

        return is_string($path) ? $path : FFI::string($path);
    }

    public function jme_jpl_file(): string
    {
        $path = $this->ffi->jme_jpl_file();

        if ($path === null) {
            return '';
        }

        return is_string($path) ? $path : FFI::string($path);
    }

    private function isExtendedApiResolutionFailure(string $message): bool
    {
        foreach (self::OPTIONAL_API_FUNCTIONS as $function) {
            if (str_contains($message, "Failed resolving C function '" . $function . "'")) {
                return true;
            }
        }

        return false;
    }

    private function defaultLibraryPath(): string
    {
        $family = PHP_OS_FAMILY;
        $arch = strtolower(php_uname('m'));
        $arch = match (true) {
            in_array($arch, ['x86_64', 'amd64'], true) => 'x64',
            in_array($arch, ['aarch64', 'arm64'], true) => 'arm64',
            default => $arch,
        };

        $file = match ($family) {
            'Windows' => 'jme.dll',
            'Darwin' => 'libjme.dylib',
            default => 'libjme.so',
        };

        $dir = '';
        if ($family === 'Windows') {
            $dir = 'jme-windows-x64-2022';
        } elseif ($family === 'Darwin') {
            if ($arch === 'arm64') {
                $darwinRelease = php_uname('r');
                $majorDarwin = (int) explode('.', $darwinRelease)[0];
                $version = ($majorDarwin >= 24) ? '15' : '14';
                $dir = 'jme-macos-arm64-' . $version;
                $libsDir = dirname(__DIR__, 2) . '/libs';
                if (!file_exists($libsDir . '/' . $dir)) {
                    $dir = ($version === '15') ? 'jme-macos-arm64-14' : 'jme-macos-arm64-15';
                }
            } else {
                $dir = 'jme-macos-x64-15';
            }
        } else {
            // Linux / other Unix
            $glibcVersion = null;
            if (function_exists('shell_exec')) {
                $output = @shell_exec('getconf GNU_LIBC_VERSION 2>&1');
                if ($output && preg_match('/glibc\s+(\d+\.\d+)/i', $output, $matches)) {
                    $glibcVersion = (float) $matches[1];
                }
            }
            if ($glibcVersion === null && file_exists('/etc/os-release')) {
                $lines = @file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $osRelease = [];
                    foreach ($lines as $line) {
                        if (str_contains($line, '=')) {
                            [$key, $val] = explode('=', $line, 2);
                            $osRelease[trim($key)] = trim($val, '"\'');
                        }
                    }
                    $id = $osRelease['ID'] ?? '';
                    $versionId = $osRelease['VERSION_ID'] ?? '';
                    if ($id === 'ubuntu' || ($osRelease['ID_LIKE'] ?? '') === 'ubuntu') {
                        if (floatval($versionId) >= 24.0) {
                            $glibcVersion = 2.39;
                        }
                    } elseif ($id === 'debian') {
                        if (floatval($versionId) >= 13.0) {
                            $glibcVersion = 2.39;
                        }
                    }
                }
            }

            $suffix = ($glibcVersion !== null && $glibcVersion >= 2.39) ? 'ubuntu24' : 'ubuntu22';
            $dir = 'jme-linux-' . $arch . '-' . $suffix;

            // Check fallback if dir does not exist
            $libsDir = dirname(__DIR__, 2) . '/libs';
            if (!file_exists($libsDir . '/' . $dir)) {
                $dir = 'jme-linux-' . $arch . '-' . ($suffix === 'ubuntu24' ? 'ubuntu22' : 'ubuntu24');
            }
        }

        return dirname(__DIR__, 2) . '/libs/' . $dir . '/' . $file;
    }

    private function prepareLibraryDirectory(string $libraryPath): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $directory = dirname($libraryPath);
        $currentPath = getenv('PATH');
        $segments = $currentPath === false ? [] : array_filter(explode(PATH_SEPARATOR, $currentPath), static fn(string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if (strcasecmp(rtrim($segment, '\\/'), rtrim($directory, '\\/')) === 0) {
                return;
            }
        }

        $updatedPath = $directory . PATH_SEPARATOR . ($currentPath === false ? '' : $currentPath);
        putenv('PATH=' . $updatedPath);
        $_ENV['PATH'] = $updatedPath;
        $_SERVER['PATH'] = $updatedPath;
    }

    private function normalizeEngine(string $engine): string
    {
        return match (strtoupper($engine)) {
            'AUTO' => 'AUTO',
            'JPL' => 'JPL',
            'MOSHIER' => 'MOSHIER',
            'VSOP_ELP_MEEUS', 'ANALYTICAL' => 'VSOP_ELP_MEEUS',
            default => strtoupper($engine),
        };
    }

    private function resolveUsableJplKernelPath(?string $ephemerisPath, ?string $jplFile): string
    {
        $candidates = [];

        foreach ([$jplFile, $ephemerisPath, $this->jme_jpl_file(), $this->jme_ephemeris_path()] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }

            if (is_dir($candidate)) {
                $matches = array_merge(
                    glob(rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.bsp') ?: [],
                    glob(rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.BSP') ?: []
                );
                sort($matches, SORT_STRING);
                if ($matches !== []) {
                    return $matches[0];
                }
            }
        }

        throw new RuntimeException(
            'ENGINE=JPL requires a readable .bsp kernel file, but no usable kernel was found in the configured JPL file or ephemeris path. '
            . 'Download kernels from: https://github.com/jayeshmepani/jpl-ephemeris/releases/tag/jpl-kernels'
        );
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function profileCall(string $name, callable $call): mixed
    {
        if (! $this->isProfileEnabled()) {
            return $call();
        }

        $start = hrtime(true);
        try {
            return $call();
        } finally {
            $this->recordProfile($name, hrtime(true) - $start);
        }
    }

    /** @param callable(): mixed $call */
    private function profileVoidCall(string $name, callable $call): void
    {
        if (! $this->isProfileEnabled()) {
            $call();
            return;
        }

        $start = hrtime(true);
        try {
            $call();
        } finally {
            $this->recordProfile($name, hrtime(true) - $start);
        }
    }

    private function isProfileEnabled(): bool
    {
        if (self::$profileEnabled !== null) {
            return self::$profileEnabled;
        }

        self::$profileEnabled = getenv('JME_FFI_PROFILE') !== false;
        if (self::$profileEnabled) {
            register_shutdown_function([self::class, 'writeProfileReport']);
            if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGINT, static function (): void {
                    self::writeProfileReport();
                    exit(130);
                });
                pcntl_signal(SIGTERM, static function (): void {
                    self::writeProfileReport();
                    exit(143);
                });
            }
        }

        return self::$profileEnabled;
    }

    private function recordProfile(string $name, int $elapsedNs): void
    {
        self::$profile[$name] ??= ['calls' => 0, 'ns' => 0];
        self::$profile[$name]['calls']++;
        self::$profile[$name]['ns'] += $elapsedNs;
    }
}
