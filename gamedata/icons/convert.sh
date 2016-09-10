#!/bin/bash

extracttar() {
    echo "Extracting blp.tar.."
    if [ ! -e current/blp.tar ]; then
        echo "current/blp.tar not found. Run fetch-icons.sh first."
        return
    fi
    rm -rf current/blp
    mkdir current/blp
    cd current/blp
    tar xf ../blp.tar
    if [ $? -ne 0 ]; then
        echo "Aborting after failed extraction"
        cd ../..
        return
    fi
    cd ../..
    #rm current/blp.tar
}

convertblps() {
    echo "Converting BLPs to PNG.."
    if [ ! -e current/blp ]; then
        echo "Couldn't find current/blp directory, aborting"
        return
    fi
    if [ ! -e BLPBuild/bin/BLPConverter ]; then
        echo "Couldn't find BLP converter in BLPBuild/bin/BLPConverter, aborting"
        return
    fi
    mkdir current/origpng
    BLPBuild/bin/BLPConverter -o current/origpng current/blp/*
    if [ $? -ne 0 ]; then
        echo "Aborting after failed conversion"
        return
    fi
    rm -rf current/blp
}

renamepngs() {
    # find the right rename..
    prename=''
    for candidate in `which -a prename rename 2>/dev/null`; do
        if [ "`$candidate -V 2>/dev/null | grep -o File::Rename`" != "" ]; then
            prename="$candidate"
            break
        fi
    done
    if [ "$prename" == "" ]; then
        echo "Could not find perl's File::Rename, aborting"
        echo "Try: sudo cpan File::Rename"
        return
    fi

    echo "Renaming PNGs to lowercase with $prename"
    if [ ! -e current/origpng ]; then
        echo "Couldn't find current/origpng directory, aborting"
        return
    fi

    $prename -v -f -E 'y/A-Z/a-z/;y/ /-/;' current/origpng/*
    if [ $? -ne 0 ]; then
        echo "Failed rename!"
    fi
}

resizepngs() {
    echo "Resizing PNGs.."
    if [ ! -e current/origpng ]; then
        echo "Couldn't find current/origpng directory, aborting"
        return
    fi

    echo "Making larges.."
    rm -rf current/large
    mkdir current/large
    mogrify -shave 4x4+4+4 -resize 56x56 -path current/large -format jpg -quality 85% current/origpng/*
    if [ $? -ne 0 ]; then
        echo "Aborting after failed resize"
        return
    fi

    echo "Making mediums.."
    rm -rf current/medium
    mkdir current/medium
    mogrify -shave 4x4+4+4 -resize 36x36 -path current/medium -format jpg -quality 85% current/origpng/*
    if [ $? -ne 0 ]; then
        echo "Aborting after failed resize"
        return
    fi

    echo "Making tinys.."
    rm -rf current/tiny
    mkdir current/tiny
    mogrify -shave 2x2+2+2 -resize 15x15 -path current/tiny -format png current/origpng/*
    if [ $? -ne 0 ]; then
        echo "Aborting after failed resize"
        return
    fi

    rm -rf current/origpng
}

makeiconsettar() {
    echo "Making iconset.tar.."
    if [ ! -e current/large ]; then
        echo "Couldn't find current/large directory, aborting"
        return
    fi

    if [ -e current/iconset.tgz ]; then
        rm current/iconset.tgz
    fi
    tar czvf current/iconset.tgz -C current large medium tiny
}

extracttar
convertblps
renamepngs
resizepngs
makeiconsettar

ls -l current/iconset.tgz
echo "Done!"