#ifndef UTILSH
#define UTILSH

#include <exception>
#include <cstdint>
#include <sstream>
#include <string>
#include <vector>
#include <algorithm>
#include <chrono>
#include <time.h>
#include "stdcc.hpp"
#include "utf8.h"

namespace sc {
    class str {
        LIBPRIV static short getCharSize(uint32_t);
    public:
        typedef int(transformFunc)(int);

        LIBPUB static uint32_t at(std::string str, int loc);
        LIBPUB static std::string transformBytes(std::string str, transformFunc func);
        LIBPUB static std::string tolower(std::string str);
        LIBPUB static std::string toupper(std::string str);
        LIBPUB static int length(std::string str);
        LIBPUB static bool valid(std::string str);
        LIBPUB static std::string fix(std::string str);
        LIBPUB static std::string substr(std::string str, int start, int end = -1);
        LIBPUB static std::string substring(std::string str, int start, int length = -1);
        LIBPUB static std::vector<std::string> split(std::string str, char delim, int count = -1);
        LIBPUB static std::vector<std::string> split(std::string str, std::string delim, int count = -1);
        LIBPUB static std::string join(std::vector<std::string> arr, std::string delim, int count = -1);
        LIBPUB static std::string trim(std::string str);
        LIBPUB static std::string ftrim(std::string str);
        LIBPUB static std::string btrim(std::string str);
    };

    class net {
    public:
        LIBPUB static std::string htonl(uint32_t hostlong);
        LIBPUB static std::string htons(uint16_t hostshort);
        LIBPUB static uint32_t ntohl(std::string netlong);
        LIBPUB static uint16_t ntohs(std::string netshort);

        LIBPUB static std::string packTime();
        LIBPUB static std::string packTime(std::chrono::time_point<std::chrono::system_clock> t);

        LIBPUB static std::string packErrorTime();
    };

    class exception : public std::exception {
        std::string details;
    public:
        LIBPUB exception() : std::exception() {
            this->details = "generic exception";
        }

        LIBPUB exception(std::exception &e) : std::exception(e) {
            this->details = "generic exception";
        }

        LIBPUB exception(std::string details) {
            this->details = details;
        }

        LIBPUB virtual const char* what() const throw() {
            return this->details.c_str();
        }
    };
}

#endif