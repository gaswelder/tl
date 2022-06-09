#import client.c

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "usage: %s <torrent-url> ...\n", argv[0]);
        return 1;
    }

    for (char **url = argv + 1; *url; url++) {
        tl_add(*url);
    }
    return 0;
}
