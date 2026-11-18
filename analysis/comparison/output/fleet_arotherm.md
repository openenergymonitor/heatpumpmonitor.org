# Arotherm fleet scan (reference year ending 2026-11-17)

- candidates 325, analysed 124, without cooling 107
- ideal efficiency space: min 0.292, median 0.472, max 0.556

## Most different similar pairs

- 478 vs 690 (7 kW): Δeta 0.114, eta 0.367/0.480, SPF 2.94/3.82, heat 14373/13912, dhw 0.091/0.126, standby 0.077/0.026
- 654 vs 662 (7 kW): Δeta 0.109, eta 0.515/0.406, SPF 4.31/3.61, heat 23085/15338, dhw 0.149/0.169, standby 0.009/0.011
- 478 vs 557 (7 kW): Δeta 0.097, eta 0.367/0.464, SPF 2.94/3.99, heat 14373/15885, dhw 0.091/0.086, standby 0.077/0.018
- 654 vs 1157 (7 kW): Δeta 0.097, eta 0.515/0.418, SPF 4.31/3.70, heat 23085/16631, dhw 0.149/0.114, standby 0.009/0.017
- 478 vs 519 (7 kW): Δeta 0.097, eta 0.367/0.463, SPF 2.94/4.02, heat 14373/17913, dhw 0.091/0.110, standby 0.077/0.017
- 719 vs 759 (7 kW): Δeta 0.094, eta 0.432/0.526, SPF 3.96/4.81, heat 13312/15504, dhw 0.053/0.049, standby 0.018/0.019
- 335 vs 384 (5 kW): Δeta 0.073, eta 0.496/0.423, SPF 4.24/3.72, heat 9885/10085, dhw 0.156/0.127, standby 0.028/0.018
- 671 vs 741 (7 kW): Δeta 0.071, eta 0.470/0.399, SPF 3.76/3.79, heat 8421/9732, dhw 0.047/0.009, standby 0.077/0.029
- 698 vs 755 (7 kW): Δeta 0.060, eta 0.415/0.475, SPF 3.48/3.91, heat 10594/16014, dhw 0.196/0.193, standby 0.023/0.020
- 671 vs 759 (7 kW): Δeta 0.057, eta 0.470/0.526, SPF 3.76/4.81, heat 8421/15504, dhw 0.047/0.049, standby 0.077/0.019

## Correlation with space ideal efficiency

- dT_flow_return_space: n 107 pearson -0.10 spearman -0.10
- defrost_cold_pct: n 104 pearson -0.06 spearman -0.18
- negheat_mild_pct: n 107 pearson +0.08 spearman -0.31
- dT_flow_outside_all: n 107 pearson -0.00 spearman -0.08
- heat_median_t: n 107 pearson +0.11 spearman +0.06
- kw: n 107 pearson +0.30 spearman +0.29
- latitude: n 107 pearson -0.21 spearman -0.21
- standby_share: n 107 pearson +0.01 spearman +0.03
- 5 kW: dT_flow_return_space r +0.06 rho +0.01; defrost_cold_pct r +0.31 rho +0.03; quintiles {'low_eta': {'dT': 3.0390296960225838, 'defrost': 3.4350149781436126}, 'high_eta': {'dT': 2.9735701134183, 'defrost': 3.4578067017656084}}
- 7 kW: dT_flow_return_space r -0.16 rho -0.14; defrost_cold_pct r -0.13 rho -0.26; quintiles {'low_eta': {'dT': 3.050806860577171, 'defrost': 5.125865860177829}, 'high_eta': {'dT': 2.72928350951445, 'defrost': 3.6405028172114653}}
- 10 kW: dT_flow_return_space r +0.03 rho -0.05; defrost_cold_pct r -0.21 rho -0.01; quintiles {'low_eta': {'dT': 3.5869111103208375, 'defrost': 2.6166229069847224}, 'high_eta': {'dT': 3.4255934798273935, 'defrost': 2.956392728474503}}
- quartiles of dT_flow_return_space: (2.241, 2.615] n 27 eta 0.470 resid sd 0.028; (2.615, 2.89] n 27 eta 0.480 resid sd 0.035; (2.89, 3.41] n 26 eta 0.480 resid sd 0.031; (3.41, 4.923] n 27 eta 0.459 resid sd 0.039
- quartiles of defrost_cold_pct: (-0.001, 2.908] n 26 eta 0.483 resid sd 0.029; (2.908, 3.643] n 26 eta 0.488 resid sd 0.042; (3.643, 4.632] n 26 eta 0.466 resid sd 0.020; (4.632, 11.64] n 26 eta 0.468 resid sd 0.042
- regression (n 104, R² 0.26): const +0.4092 (t +9.3); dT_flow_return_space -0.0124 (t -1.7); defrost_cold_pct +0.0030 (t +1.2); heat_median_t +0.0046 (t +1.2); dT_flow_outside_all +0.0021 (t +1.6); kw_7_vs_5 -0.0110 (t -1.3); kw_10_vs_5 +0.0494 (t +4.4); kw_12_vs_5 +0.0209 (t +1.4)
