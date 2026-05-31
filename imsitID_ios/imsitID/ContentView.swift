//
//  ContentView.swift
//  imsitID
//

import SwiftUI

struct ContentView: View {
    @EnvironmentObject var appState: AppState
    @State private var showGroupSelection = false
    @State private var showTeacherSelection = false
    @State private var showSearch = false
    @State private var searchText = ""
    
    var body: some View {
        ZStack {
            // Background gradient
            LinearGradient(
                colors: [
                    Color(red: 0.043, green: 0.071, blue: 0.125),
                    Color(red: 0.208, green: 0.208, blue: 0.208)
                ],
                startPoint: .top,
                endPoint: .bottom
            )
            .ignoresSafeArea()
            
            VStack(spacing: 0) {
                // Header
                HeaderView(
                    showSearch: $showSearch,
                    searchText: $searchText,
                    onRefresh: {
                        Task { @MainActor in
                            await appState.refreshSchedule()
                        }
                    }
                )
                .padding(.top, 8)
                
                // Selected Group/Teacher Label
                if appState.selectedGroup != nil || appState.selectedTeacher != nil {
                    HStack {
                        Image(systemName: appState.viewMode == .group ? "person.3.fill" : "person.fill")
                            .font(.system(size: 14))
                            .foregroundColor(.white.opacity(0.7))
                        Text(appState.selectedGroup ?? appState.selectedTeacher ?? "")
                            .font(.system(size: 14, weight: .medium))
                            .foregroundColor(.white.opacity(0.9))
                        Spacer()
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 8)
                }
                
                // Search Results
                if showSearch {
                    SearchResultsView(searchText: $searchText, isPresented: $showSearch)
                }
                
                ScrollView {
                    VStack(spacing: 16) {
                        if appState.selectedGroup == nil && appState.selectedTeacher == nil {
                            // Selection prompt
                            SelectionPromptView(
                                onSelectGroup: { showGroupSelection = true },
                                onSelectTeacher: { showTeacherSelection = true }
                            )
                        } else {
                            // Loading indicator
                            if appState.isLoading {
                                ProgressView()
                                    .progressViewStyle(CircularProgressViewStyle(tint: .white))
                                    .scaleEffect(1.5)
                                    .padding(.vertical, 40)
                            }
                            
                            // Error message
                            if let errorMessage = appState.errorMessage {
                                Text("Ошибка: \(errorMessage)")
                                    .font(.system(size: 14))
                                    .foregroundColor(.red)
                                    .padding()
                                    .glassEffect(cornerRadius: 12)
                            }
                            
                            // Current and next lesson cards
                            if appState.currentLesson != nil || appState.nextLesson != nil {
                                CurrentLessonsView()
                                    .id("currentLessons-\(appState.currentDay)-\(appState.currentWeek)")
                            }
                            
                            // Week and day selector
                            WeekDaySelectorView()
                            
                            // Schedule list
                            ScheduleListView()
                                .id("schedule-\(appState.currentDay)-\(appState.currentWeek)")
                        }
                    }
                    .padding(.horizontal, 16)
                    .padding(.top, 8)
                    .padding(.bottom, 20)
                }
                .scrollIndicators(.hidden)
            }
        }
        .sheet(isPresented: $showGroupSelection) {
            GroupSelectionView(isPresented: $showGroupSelection)
                .environmentObject(appState)
        }
        .sheet(isPresented: $showTeacherSelection) {
            TeacherSelectionView(isPresented: $showTeacherSelection)
                .environmentObject(appState)
        }
        .onAppear {
            if appState.selectedGroup == nil && appState.selectedTeacher == nil {
                showGroupSelection = true
            } else {
                // Загружаем расписание если уже выбрана группа/преподаватель
                Task { @MainActor in
                    await appState.loadFullSchedule()
                }
            }
            Task { @MainActor in
                await appState.loadAvailableGroups()
                await appState.loadAvailableTeachers()
            }
        }
        .refreshable {
            await appState.refreshSchedule()
        }
    }
}

#Preview {
    ContentView()
        .environmentObject(AppState())
}

