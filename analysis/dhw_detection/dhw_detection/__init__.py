"""Predict when a heat pump is heating domestic hot water, from its 30 s electricity, heat, flow and return
temperature and flow rate. See README.md for use and INTEGRATION.md for the exact processing.

    from dhw_detection import load_models, predict_system, cycles

    models = load_models()                     # the three trained copies, on the CPU
    out, settings = predict_system(raw, models)  # raw: one system's 30 s data, UTC index
    periods = cycles(out)
"""

from .model import MODEL_NAME, load_models
from .predict import THRESHOLD, WINDOW, cycles, predict_probability, predict_system, signals_present, summary
from .prepare import FLAG, SIGNALS, prepare

__all__ = ["MODEL_NAME", "THRESHOLD", "WINDOW", "FLAG", "SIGNALS", "load_models", "prepare", "predict_probability",
           "predict_system", "cycles", "signals_present", "summary"]
