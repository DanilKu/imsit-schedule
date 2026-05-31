//
//  HeaderView.swift
//  imsitID
//

import SwiftUI

struct HeaderView: View {
    @Binding var showSearch: Bool
    @Binding var searchText: String
    let onRefresh: () -> Void
    
    var body: some View {
        VStack(spacing: 0) {
            if showSearch {
                SearchHeaderView(searchText: $searchText, showSearch: $showSearch)
            } else {
                DefaultHeaderView(showSearch: $showSearch, onRefresh: onRefresh)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 12)
    }
}

struct DefaultHeaderView: View {
    @Binding var showSearch: Bool
    let onRefresh: () -> Void
    
    var body: some View {
        HStack {
            Text("imsitID - Расписание")
                .font(.system(size: 15, weight: .semibold))
                .foregroundStyle(
                    LinearGradient(
                        colors: [.white, .gray],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                )
            
            Spacer()
            
            HStack(spacing: 28) {
                Button(action: { showSearch = true }) {
                    Image(systemName: "magnifyingglass")
                        .font(.system(size: 20))
                        .foregroundColor(.white.opacity(0.8))
                }
                
                Button(action: onRefresh) {
                    Image(systemName: "arrow.clockwise")
                        .font(.system(size: 20))
                        .foregroundColor(.white.opacity(0.8))
                }
            }
        }
    }
}

struct SearchHeaderView: View {
    @Binding var searchText: String
    @Binding var showSearch: Bool
    @FocusState private var isFocused: Bool
    
    var body: some View {
        HStack {
            TextField("Поиск...", text: $searchText)
                .focused($isFocused)
                .foregroundColor(.white)
                .font(.system(size: 16, weight: .medium))
            
            Button(action: {
                showSearch = false
                searchText = ""
            }) {
                Image(systemName: "xmark")
                    .font(.system(size: 18))
                    .foregroundColor(.white)
            }
        }
        .onAppear {
            isFocused = true
        }
    }
}

