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

    /**
     * Compare functions do not take into account prerelease versions
     * @param versionA
     * @param versionB
     * @param comparator
     * @returns {boolean}
     */
    compare(versionA, versionB, comparator = '=') {
        const partsA = this.matchVersion(versionA);
        const partsB = this.matchVersion(versionB);

        if(partsA === null || partsB === null) {
            return false;
        }

        switch(comparator) {
            case '=':
            case '==':
            case '===':
            case 'eq':
                return partsA.major === partsB.major
                    && partsA.minor === partsB.minor
                    && partsA.patch === partsB.patch
                    && partsA.build === partsB.build
            case '!=':
            case '!==':
            case 'neq':
                return !(partsA.major === partsB.major
                    && partsA.minor === partsB.minor
                    && partsA.patch === partsB.patch
                    && partsA.build === partsB.build)
            case '>':
            case 'gt':
                if(partsA.major > partsB.major) {
                    return true;
                }
                if(partsA.minor > partsB.minor) {
                    return true;
                }
                if(partsA.patch > partsB.patch) {
                    return true;
                }
                return partsA.build > partsB.build;
            case '>=':
            case 'gte':
                if(partsA.major < partsB.major) {
                    return false;
                }
                if(partsA.minor < partsB.minor) {
                    return false;
                }
                if(partsA.patch < partsB.patch) {
                    return false;
                }
                return partsA.build >= partsB.build;
            case '<':
            case 'lt':
                if(partsA.major < partsB.major) {
                    return true;
                }
                if(partsA.minor < partsB.minor) {
                    return true;
                }
                if(partsA.patch < partsB.patch) {
                    return true;
                }
                return partsA.build < partsB.build;
            case '<=':
            case 'lte':
                if(partsB.major > partsA.major) {
                    return true;
                }
                if(partsB.minor > partsA.minor) {
                    return true;
                }
                if(partsB.patch > partsA.patch) {
                    return true;
                }
                return partsB.build >= partsA.build;
        }

        return false;
    },

    matchVersion(version) {
        const match = version.match(/(?<version>(?<major>\d+)\.?(?<minor>\d+)\.?(?<patch>\d+)\.?(?<build>\d*))-?(?<prerelease>[a-z]+)?\.?(?<prereleaseDigits>\d+(?:.\d+)*)?/i);

        if(match === null) {
            console.warn(`${version} is not a valid version string.`);
            return null;
        }

        const groups = match.groups;

        ['major', 'minor', 'patch', 'build'].forEach(part => {
            groups[part] = parseInt(groups[part]) || 0;
        })

        return groups;
    },

    getHumanReadableVersion(version) {
        const match = this.matchVersion(version);

        if (match === null) {
            return version;
        }

        let output = `v${match.version}`;

        if (match.prerelease) {
            output += ` ${this.getHumanReadablePrereleaseText(match.prerelease)}`;
        } else {
            output += ' Stable Version';
        }

        if (match.prereleaseDigits) {
            output += ` ${match.prereleaseDigits}`;
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
