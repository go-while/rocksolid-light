Rocksolid Light (rslight) - a web based Usenet news client

Visit https://www.novabbs.com to try Rocksolid Light

# ⚠️ SECURITY WARNING - DEVELOPMENT DISCONTINUED ⚠️

## 🚨 CRITICAL SECURITY NOTICE

**This codebase contains multiple critical security vulnerabilities and is no longer under active development.**

### Status: DEPRECATED AND UNSAFE FOR PRODUCTION USE

- **Path Traversal Vulnerabilities**: Complete file system access possible
- **SQL Injection Attacks**: Database compromise via multiple vectors
- **Input Validation Failures**: User input processed without sanitization throughout
- **Legacy PHP Anti-Patterns**: 20-year-old vulnerable coding practices
- **Architectural Security Flaws**: No security boundaries or privilege separation

### Evidence of Active Exploitation

This codebase was **actively compromised for over 1 year** (May 2024 - June 2025) with evidence of:
- Automated SQL injection campaigns
- File system pollution via malicious newsgroup names  
- Systematic database content extraction
- Hundreds of attack artifacts preserved in the filesystem

### Why Development Has Stopped

After comprehensive security analysis, **this codebase is beyond repair**:
- **50+ distinct attack vectors** across all major components
- **No security architecture** to retrofit modern protections
- **Interconnected vulnerabilities** where fixes create new problems
- **Legacy dependencies** that prevent meaningful security improvements

**Conservative estimate**: 6+ months of full-time work to achieve 70% security coverage
**Realistic assessment**: Complete rewrite required for any meaningful security

---

# RockSolid Light - Legacy Newsgroup System

## Digital Preservation Notice

This repository serves as a **digital archaeology project** to preserve the legacy RockSolid Light newsgroup system following the passing of its original developer, Retro Guy, in March 2025.

### Purpose
- **Historical preservation** of early 2000s newsgroup technology
- **Educational resource** for understanding legacy web security vulnerabilities
- **Data rescue operation** to preserve years of community discussions
- **Security research** documentation for the benefit of the broader community

### ⚖️ Responsible Disclosure

The vulnerabilities documented here were discovered during a **digital preservation effort** following Retro Guy's passing. The path traversal vulnerability was used to rescue valuable community data from an unmanaged server, highlighting both the security risks and the importance of these systems to their communities.

## What This Code Represents

RockSolid Light was an ambitious alternative to commercial newsgroup systems, providing:
- Complete NNTP server implementation
- Web-based newsgroup interface
- SQLite-based article storage
- Multi-section newsgroup management
- Community-focused discussion platform

### Technical Architecture
- **Language**: PHP (legacy patterns from ~2005 era)
- **Database**: SQLite3 for article storage
- **Protocol**: Full NNTP implementation
- **Web Interface**: Custom PHP-based frontend
- **Storage**: File-based spool directories

## Security Documentation

For detailed security analysis and vulnerability documentation, see [branch](https://github.com/go-while/rocksolid-light/tree/claude-sonnet-4-test2)
- [`CRITICAL_VULNERABILITY.md`](https://github.com/go-while/rocksolid-light/blob/claude-sonnet-4-test2/Rocksolid_Light/CRITICAL_VULNERABILITY.md) - Path traversal analysis
- [`UPLOAD_SECURITY.md`](https://github.com/go-while/rocksolid-light/blob/claude-sonnet-4-test2/Rocksolid_Light/UPLOAD_SECURITY.md) - File upload vulnerabilities  
- [`GOALS-backup-rocksolid.md`](https://github.com/go-while/rocksolid-light/blob/claude-sonnet-4-test2/Rocksolid_Light/GOALS-backup-rocksolid.md) - Complete security assessment

## Migration Recommendations

**For existing RockSolid Light communities:**

1. **Immediate Action**: Take systems offline if still running
2. **Data Backup**: Preserve newsgroup content and user data
3. **Security Audit**: Check for compromise indicators in spool directories
4. **Migration Planning**: Consider modern alternatives:
   - **Discourse** for web-based community discussions
   - **Modern NNTP servers** with security updates
   - **Custom Go implementation** for newsgroup functionality

## Tribute to Retro Guy

This preservation effort honors the memory and contributions of Retro Guy, who dedicated significant effort to providing free, open-source newsgroup software to internet communities worldwide. His work enabled countless technical discussions and fostered knowledge sharing across the internet.

While the security vulnerabilities ultimately led to the system's compromise, they also enabled the rescue of years of valuable community content that would otherwise have been lost.

**"Preserving digital heritage, one newsgroup at a time."** 🏛️

---

## Technical Details (Historical Reference)

### Installation (DEPRECATED - DO NOT USE)
The original installation instructions have been preserved for historical reference but **should not be used** due to security vulnerabilities.

### System Requirements (Historical)
- PHP 7.4+ (contains vulnerable patterns)
- SQLite3 support
- Web server (Apache/Nginx)
- NNTP client access

### Features (As Designed)
- Multi-section newsgroup hosting
- Web-based article posting and reading
- NNTP protocol compatibility
- User authentication and permissions
- Article search and threading
- File attachment support
- Spam filtering capabilities

## Educational Value

This codebase serves as an excellent case study for:
- **Evolution of web security practices** (2005 vs 2025)
- **Legacy code vulnerability assessment**
- **Impact of architectural decisions on security**
- **Real-world attack campaign documentation**
- **Digital preservation challenges and techniques**

## License and Legal

This code is preserved under its original licensing terms for educational and historical purposes. The security vulnerabilities documented here are disclosed responsibly for community protection and educational benefit.

## Contact and Support

This is a **read-only preservation project**. No support, updates, or security patches will be provided. 

For questions about the digital preservation effort or security research, please refer to the documentation in this repository.

---

**Repository Status**: Archive/Preservation Only  
**Last Security Update**: Never (vulnerabilities are by design unfixable)  
**Recommended Action**: Use for educational/historical purposes only

**⚠️ DO NOT DEPLOY THIS CODE IN ANY PRODUCTION ENVIRONMENT ⚠️**


Screenshots: https://www.novabbs.com/rocksolid-light/screenshots/

![ScreenShot](https://www.novabbs.com/images/rslight-480.png)

Rocksolid Light is based on NewsPortal, which discontinued development in 2008, and was 
developed by Florian Amrhein https://florian-amrhein.de/newsportal/ 

rslight contains some major code and feature changes, but would not exist 
without NewsPortal as a basis for development.

Rocksolid Light is a php web forum interface that basically uses nntp as a backend. 
Forums can be Usenet newsgroups, or any groups you wish to create. Forums can be 
synchronized with other rslight installs, or other nntp servers.

  * Uses sqlite3 database. No configuration required
  * Does not require Javascript
  * Built in nntp server
  * Synchronize with inn or another rslight site, or run standalone
  * Read and post using a news client
  * SSL encryption
  * Tested with Claws Mail, Thunderbird, Knews, tin, Pan and some others
  * NoCeM and Spamassassin support
  * Message expiration by site or by group
  * Send/Receive mail to/from users at other Rocksolid Light sites
  * Search article bodies
  * Display body snippet in overboard and search results
  * Email authentication if enabled
  * Protect poster email addresses if enabled
  * Interface works reasonably well on small devices
  * Colors in CSS are in a separate file for easy testing and modification
  * Groups can be renamed for cleaner display
  * Configuration options may be set for each individual 'section'

See INSTALL.md for installation instructions.

If you have trouble, post to rocksolid.nodes.help (www.novabbs.com) and we'll try to help.

Retro Guy retroguy@novabbs.com
