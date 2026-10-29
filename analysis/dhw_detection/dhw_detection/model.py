"""The hot-water network and its trained weights.

A stack of dilated 1-D convolutions (each step sees about 2 hours either side) with a
bidirectional GRU over the whole 24 h window on top. Input (batch, 10, 2880): five scaled
signals and five missing-value channels. Output: one hot-water logit per 30 s step.

Three copies were trained with different random seeds; predictions use the mean of all three.
"""

from pathlib import Path

import torch
import torch.nn as nn

MODEL_NAME = "p19_gru_rescued"
WEIGHTS = Path(__file__).resolve().parents[1] / "weights"
SEEDS = (0, 1, 2)


def block(ch, dilation, kernel=5):
    return nn.Sequential(nn.Conv1d(ch, ch, kernel, padding=(kernel - 1) // 2 * dilation, dilation=dilation),
                         nn.GroupNorm(8, ch), nn.GELU())


class Net(nn.Module):
    """Residual dilated convolutions, then a bidirectional GRU added to their output, then one logit per step."""

    def __init__(self, in_ch=10, ch=48, dilations=(1, 2, 4, 8, 16, 32, 64), kernel=5):
        super().__init__()
        self.inp, self.out = nn.Conv1d(in_ch, ch, 1), nn.Conv1d(ch, 1, 1)
        self.blocks = nn.ModuleList(block(ch, d, kernel) for d in dilations)
        self.gru = nn.GRU(ch, ch // 2, batch_first=True, bidirectional=True)

    def forward(self, x):
        h = self.inp(x)
        for b in self.blocks:
            h = h + b(h)
        h = h + self.gru(h.transpose(1, 2))[0].transpose(1, 2)
        return self.out(h).squeeze(1)


def load_models(device="cpu", name=MODEL_NAME, folder=WEIGHTS):
    """The three trained copies, ready to predict."""
    models = []
    for seed in SEEDS:
        net = Net()
        net.load_state_dict(torch.load(Path(folder) / f"{name}_seed{seed}.pth", map_location=device, weights_only=True))
        models.append(net.to(device).eval())
    return models
