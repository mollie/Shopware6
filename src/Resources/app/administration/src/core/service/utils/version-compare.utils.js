export default {
    equals(versionA, versionB) {
        return this.compare(versionA, versionB, '=');
    },
    notEquals(versionA, versionB) {
        return this.compare(versionA, versionB, '!=');
    },
    greater(versionA, versionB) {
        return this.compare(versionA, versionB, '>');
    },
    greaterOrEqual(versionA, versionB) {
        return this.compare(versionA, versionB, '>=');
    },
    lesser(versionA, versionB) {
        return this.compare(versionA, versionB, '<');
    },
    lesserOrEqual(versionA, versionB) {
        return this.compare(versionA, versionB, '<=');
    },

    compare(versionA, versionB, comparator = '=') {
        const partsA = this.matchVersion(versionA);
        const partsB = this.matchVersion(versionB);

        if(partsA === null) {
            console.warn(`${versionA} is not a valid version string.`);
            return false;
        }

        if(partsB === null) {
            console.warn(`${versionA} is not a valid version string.`);
            return false;
        }

        switch(comparator) {
            case '=':
            case '==':
            case '===':
            case 'eq':
                return partsA.groups.version === partsB.groups.version;
            case '!=':
            case '!==':
            case 'neq':
                return partsA.groups.version !== partsB.groups.version;
            case '>':
            case 'gt':
                if(partsA.groups.major > partsB.groups.major) {
                    return true;
                }
                if(partsA.groups.minor > partsB.groups.minor) {
                    return true;
                }
                if(partsA.groups.patch > partsB.groups.patch) {
                    return true;
                }
                return partsA.groups.build > partsB.groups.build;
            case '>=':
            case 'gte':
                if(partsA.groups.major < partsB.groups.major) {
                    return false;
                }
                if(partsA.groups.minor < partsB.groups.minor) {
                    return false;
                }
                if(partsA.groups.patch < partsB.groups.patch) {
                    return false;
                }
                return partsA.groups.build >= partsB.groups.build;
            case '<':
            case 'lt':
                if(partsA.groups.major < partsB.groups.major) {
                    return true;
                }
                if(partsA.groups.minor < partsB.groups.minor) {
                    return true;
                }
                if(partsA.groups.patch < partsB.groups.patch) {
                    return true;
                }
                return partsA.groups.build < partsB.groups.build;
            case '<=':
            case 'lte':
                if(partsA.groups.major > partsB.groups.major) {
                    return false;
                }
                if(partsA.groups.minor > partsB.groups.minor) {
                    return false;
                }
                if(partsA.groups.patch > partsB.groups.patch) {
                    return false;
                }
                return partsA.groups.build <= partsB.groups.build;
        }

        return false;
    },

    matchVersion(version) {
        return version.match(/(?<version>(?<major>\d+)\.?(?<minor>\d+)\.?(?<patch>\d+)\.?(?<build>\d*))-?(?<prerelease>[a-z]+)?\.?(?<prereleaseDigits>\d+(?:.\d+)*)?/i);
    },

    getHumanReadableVersion(version) {
        const match = this.matchVersion(version);

        if (match === null) {
            return version;
        }

        let output = `v${match.groups.version}`;

        if (match.groups.prerelease) {
            output += ` ${this.getHumanReadablePrereleaseText(match.groups.prerelease)}`;
        } else {
            output += ' Stable Version';
        }

        if (match.groups.prereleaseDigits) {
            output += ` ${match.groups.prereleaseDigits}`;
        }

        return output;
    },

    getHumanReadablePrereleaseText(text) {
        switch (text) {
            case 'dp':
                return 'Developer Preview';
            case 'rc':
                return 'Release Candidate';
            case 'dev':
                return 'Developer Version';
            case 'ea':
                return 'Early Access';
            default:
                return text;
        }
    },
}
