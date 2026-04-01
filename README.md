# ePT

Open Source e-Proficiency Testing (ePT) software for managing PT schemes including HIV Serology, Viral Load, EID, Recency, Covid-19, Tuberculosis, and custom tests.

## Quick Start

### Docker (recommended)

```bash
git clone https://github.com/deforay/ept.git
cd ept
docker compose up --build
```

Access the admin panel at [http://localhost/admin](http://localhost/admin).

### Ubuntu

```bash
cd ~
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod +x ept-setup.sh
sudo ./ept-setup.sh
```

## Documentation

Full setup guides (Docker, Ubuntu, Windows), architecture docs, and training materials are available at:

**[deforay.github.io/ept](https://deforay.github.io/ept/)**

## License

[GNU Affero General Public License v3.0](LICENSE.md) (AGPL-3.0)

## Contact

[amit@deforay.com](mailto:amit@deforay.com) | [GitHub Issues](https://github.com/deforay/ept/issues)
