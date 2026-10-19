# The open question: why doesn't load show up in annual SPF?

*Status: open. The experiment at the end is planned but hasn't been run.*

Manufacturer data says a heat pump is much more efficient at low
compressor speed than flat out, at the same water and air temperatures.
The H\* metric (see [README.md](README.md), section 5) builds that in, and
on the real fleet it made prediction worse. This doc sets out what we know
about why, and how we could find out.

## Some definitions

These get mixed up easily, so to be precise:

- **r**: instantaneous load ratio, heat output divided by rated capacity.
- **r̄**: a system's heat-weighted annual mean of r. Fleet median 0.66,
  sd 0.15. (The *time*-weighted mean while running is lower: median 0.51,
  IQR 0.43–0.59.)
- **Utilisation**: annual heat divided by capacity × hours in the year,
  off-time included. Fleet median 0.22. It isn't used anywhere below.
  A system that rarely runs but goes flat out when it does has low
  utilisation and high r̄.
- **H_fixed**: heat-weighted harmonic Carnot COP with fixed +2/−6 K
  offsets. This only accounts for temperatures.
- **H\***: the same, with offsets that grow with load (+3r/−8r K).
- **P = H\*/H_fixed**: how much the load model adjusts each system's
  year. Above 1 means the heat was delivered gently, below 1 means the
  machine was run hard. Fleet sd 4.7%.

r̄ and utilisation are only loosely related (r ≈ +0.55). Both follow from
sizing, but at the same r̄ utilisation still ranges from 0.10 to 0.44
depending on climate, house and schedule.

![utilisation vs mean load ratio](figures/utilisation_vs_rbar.png)

## The test

If every machine achieved the same fraction q of its true ideal COP, then
SPF = q·H\*. Dividing both sides by H_fixed cancels the temperatures,
which are most of the signal, and leaves just the load part:

```
SPF / H_fixed = q · P
```

Plotted against P, systems should then line up along a slope of
q ≈ 0.45. Gently run systems should beat the temperature-only benchmark,
and hard-run ones fall short of it, by about 0.2 SPF per standard
deviation of P.

**The observed slope is +0.05. It's flat.** The over- and
under-performance the load model predicts isn't there.

![the P test](figures/hstar_p_test.png)

*Panel A: SPF against H\* is dominated by temperature (colour = weighted
ΔT). Panel B: the load part on its own. The orange line is what identical
machines would show, and the fleet is flat.*

Other checks agree. Adding mean load ratio to ΔT doesn't help (cv R²
0.588 vs 0.595), and in a fit with both H_fixed and H\*, H\* gets a
coefficient of about zero.

## Two explanations

From annual data we can't tell these apart:

1. **The penalty is real but something cancels it.** Machines really are
   more efficient at low load, but systems run at low load are worse in
   some other way that roughly cancels the benefit.
2. **There's nothing to cancel.** The load model overstates how much the
   steady-state benefit is worth over a real year of operation, so flat
   simply means flat.

The simulator can't decide between these, because it *is* the load model.
That's why it saw a big gain from H\*: its machines were identical by
construction.

### What's been ruled out as the cancelling effect

- **Standby.** It's small (median 2.4% of electricity), and using running
  COP instead of combined COP leaves the load slope unchanged. Whatever is
  going on happens while the compressor is running.
- **DHW share.** It's uncorrelated with SPF/H\* and barely changes with
  load.
- **Heat meter ΔT error.** Controlling for flow−return ΔT barely moves the
  load slope.

### What's still in the running

- **Efficiency drops off at minimum modulation.** The Arotherm data below
  shows COP turning over at the lowest speed. Systems at low r̄ spend more
  of their time down there.
- **Installation quality goes with sizing.** Well-sized systems may simply
  be better designed and commissioned in general.
- **The benefit doesn't hold up in real installs.** For example, part-load
  flow rates and water ΔT differ from the test conditions. This would be
  explanation 2.

## How big a lever was it anyway?

Not huge, even before the cancellation. P has an sd of 4.7%, worth about
±0.19 SPF, against a fleet SPF sd of 0.49, so at most about 15% of the
variance. Real installs sit in a narrow band of load. The simulator's
designs spread far wider, which is why it overstated the effect. Roughly,
temperatures explain 0.60 of the variance, load explains about 0.15 at
best and about zero in practice, and the rest is machine and install
quality plus noise.

## The experiment: measure the compressor map in the field

The fleet has **150 Arotherm+ systems** in the clean scan (58 × 5 kW,
64 × 7 kW, 12 × 10 kW, 9 × 12 kW, 6 × 3.5 kW), and Vaillant publishes COP
per compressor speed. That's a ground truth we can check field data
against.

Arotherm 5 kW datasheet, 35/30 °C flow/return, COP (output kW):

| Outside | 120 rps | 90 rps | 70 rps | 50 rps | 40 rps | 30 rps |
|---|---|---|---|---|---|---|
| −7 °C | 2.7 (6.2) | 3.0 (4.5) | 2.9 (3.3) | 3.1 (2.2) | 3.4 (1.7) | — |
| +2 °C | 3.1 (7.3) | 3.4 (5.5) | 3.5 (4.2) | 3.7 (3.0) | 4.0 (2.4) | 3.9 (2.0) |
| +7 °C | 3.3 (8.0) | 3.7 (6.2) | 3.9 (4.9) | 4.3 (3.7) | 4.6 (2.9) | 4.1 (2.1) |

The part-load benefit is large (3.3 → 4.6 at +7 °C), at least as big as
the +3r/−8r model assumes. It turns over at 30 rps. And the "5 kW" badge
covers 2.1–8.0 kW of real output. Below 2.1 kW the machine has to cycle.

**The plan:**

1. Extend the stable-episode detector in `feed_scan_2.php` into a fleet
   extractor for the Arotherm systems. An episode is at least 10 minutes
   with the compressor on and flowT steady. Record mean COP, output,
   flowT, returnT, outsideT and flow rate for each.
2. Plot episode COP against output in bins of outside and flow
   temperature, and see whether field data reproduces the datasheet.
   Since flow temperatures rarely sit at exactly 35 °C, it's probably
   easier to plot COP / Carnot against output, which uses every episode.
3. For each system, weight its own episode curve by its annual histogram
   (from `hstar_hist.csv`) to get the SPF its steady running implies.
4. Compare that with its measured running SPF. The gap is everything
   steady running misses: cycling, defrost, DHW and transients. Then see
   whether the gap grows as r̄ falls.

**What the results would mean:**

- **Field curves match the datasheet:** explanation 1. The benefit is
  real, so the cancelling effect has to live outside steady running, and
  the step 4 gap should grow at low r̄ by around 0.2 SPF per sd of P.
  Spread between identical machines at the same operating point then
  measures install quality directly.
- **Field curves are flatter than the datasheet:** explanation 2. The
  benefit doesn't survive in real installs, and that by itself explains
  why H\* failed.
- **Low-load systems have few low-output episodes at all,** because they
  cycle instead of modulating. That would be informative in itself.

Practical notes:

- We don't have compressor speed in the feeds, so compare on output kW.
  Within one badge size that also sidesteps the capacity question.
- `feed_scan_2`'s stability thresholds (flowT sd < 0.01 K over 10 min,
  slope < 0.5 K/h, elec > 150 W) were tuned on one system and will
  probably need loosening. It's also worth requiring stable returnT, flow
  rate and outsideT.
- Separate out DHW episodes by flow temperature (over about 45 °C). They
  give a second, free comparison at DHW temperatures.
- Defrost recovery drops out by itself, since flowT isn't stable.

---

*Files: `scripts/feed_scan/feed_scan_2.php` (episode detector),
`feed_scan_4.php` (fleet H\* scan), `feed_scan_5.php` (histograms);
`analyse_hstar_fleet.py`, `analyse_hstar_offsets.py`; `hstar_fleet.csv`,
`hstar_fleet_hist.csv`, `hstar_hist.csv`.*
